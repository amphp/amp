<?php

namespace Amp;

class LibeventReactor implements SignalReactor {
    private $base;
    private $watchers = [];
    private $immediates = [];
    private $lastWatcherId = "a";
    private $enabledWatcherCount = 0;
    private $resolution = 1000;
    private $isRunning = false;
    private $isGCScheduled = false;
    private $garbage = [];
    private $gcEvent;
    private $stopException;
    private $onError;
    private $onCallbackResolution;

    private static $instanceCount = 0;

    public function __construct() {
        if (!extension_loaded('libevent')) {
            throw new \RuntimeException('The pecl-libevent extension is required to use the LibeventReactor.');
        }

        $this->base = event_base_new();
        $this->gcEvent = event_new();
        event_timer_set($this->gcEvent, [$this, 'collectGarbage']);
        event_base_set($this->gcEvent, $this->base);
        $this->onCallbackResolution = function($e = null, $r = null) {
            if (empty($e)) {
                return;
            } elseif ($onError = $this->onError) {
                $onError($e);
            } else {
                throw $e;
            }
        };
        self::$instanceCount++;
    }

    /**
     * {@inheritDoc}
     * @throws \Exception Will throw if code executed during the event loop throws
     */
    public function run(callable $onStart = null) {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        if ($onStart) {
            $this->immediately($onStart);
        }

        while ($this->isRunning) {
            if ($this->immediates && !$this->doImmediates()) {
                break;
            }
            if (empty($this->enabledWatcherCount)) {
                break;
            }
            event_base_loop($this->base, EVLOOP_ONCE | (empty($this->immediates) ? 0 : EVLOOP_NONBLOCK));
        }

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    private function doImmediates() {
        $immediates = $this->immediates;
        foreach ($immediates as $watcherId => $callback) {
            try {
                $this->enabledWatcherCount--;
                unset(
                    $this->immediates[$watcherId],
                    $this->watchers[$watcherId]
                );
                $result = $callback($this, $watcherId);
                if ($result instanceof \Generator) {
                    resolve($result, $this)->when($this->onCallbackResolution);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }

            if (!$this->isRunning) {
                // If one of the immediately watchers stops the reactor break out of the loop
                return false;
            }
        }

        return $this->isRunning;
    }

    /**
     * {@inheritDoc}
     */
    public function tick($noWait = false) {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;

        if (empty($this->immediates) || $this->doImmediates()) {
            $flags = $noWait || !empty($this->immediates) ? (EVLOOP_ONCE | EVLOOP_NONBLOCK) : EVLOOP_ONCE;
            event_base_loop($this->base, $flags);
        }

        $this->isRunning = false;

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function stop() {
        event_base_loopexit($this->base);
        $this->isRunning = false;
    }

    /**
     * {@inheritDoc}
     */
    public function immediately(callable $callback): string {
        $this->enabledWatcherCount++;
        $watcherId = $this->lastWatcherId++;
        $this->immediates[$watcherId] = $callback;

        $watcher = new \StdClass;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::IMMEDIATE;
        $watcher->callback = $callback;
        $watcher->isEnabled = true;

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, int $msDelay): string {
        $this->enabledWatcherCount++;
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;

        $watcher = new LibeventTimerWatcher;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::TIMER_ONCE;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;
        $watcher->wrapper = $this->wrapOnceCallback($watcher);
        $watcher->isEnabled = true;

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $msDelay);

        return $watcherId;
    }

    private function wrapOnceCallback(LibeventWatcher $watcher) {
        return function() use ($watcher) {
            try {
                $callback = $watcher->callback;
                $watcherId = $watcher->id;
                $result = $callback($this, $watcherId);
                if ($result instanceof \Generator) {
                    resolve($result, $this)->when($this->onCallbackResolution);
                }
                $this->cancel($watcherId);
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    private function handleRunError(\Exception $e) {
        try {
            if (empty($this->onError)) {
                $this->stopException = $e;
                $this->stop();
            } else {
                $handler = $this->onCallbackResolution;
                $handler($e);
            }
        } catch (\Exception $e) {
            $this->stopException = $e;
            $this->stop();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function repeat(callable $callback, int $msDelay): string {
        $this->enabledWatcherCount++;
        $watcherId = $this->lastWatcherId++;
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;
        $eventResource = event_new();

        $watcher = new LibeventTimerWatcher;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::TIMER_REPEAT;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;
        $watcher->wrapper = $this->wrapRepeatingCallback($watcher);
        $watcher->isEnabled = true;

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $msDelay);

        return $watcherId;
    }

    private function wrapRepeatingCallback(LibeventWatcher $watcher): \Closure {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $eventResource = $watcher->eventResource;
        $msDelay = $watcher->msDelay;

        return function() use ($callback, $eventResource, $msDelay, $watcherId) {
            try {
                $result = $callback($this, $watcherId);
                if ($result instanceof \Generator) {
                    resolve($result, $this)->when($this->onCallbackResolution);
                }

                // If the watcher cancelled itself this will no longer be set
                if (isset($this->watchers[$watcherId])) {
                    event_add($eventResource, $msDelay);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, bool $enableNow = true): string {
        return $this->watchIoStream($stream, Watcher::IO_READER, $callback, $enableNow);
    }

    /**
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, bool $enableNow = true): string {
        return $this->watchIoStream($stream, Watcher::IO_WRITER, $callback, $enableNow);
    }

    private function watchIoStream($stream, $type, callable $callback, $enableNow): string {
        $this->enabledWatcherCount += $enableNow;
        $watcherId = (string) $this->lastWatcherId++;
        $eventResource = event_new();
        $flags = EV_PERSIST;
        $flags |= ($type === Watcher::IO_READER) ? EV_READ : EV_WRITE;

        $watcher = new LibeventIoWatcher;
        $watcher->id = $watcherId;
        $watcher->type = $type;
        $watcher->stream = $stream;
        $watcher->callback = $callback;
        $watcher->wrapper = $this->wrapStreamCallback($watcher);
        $watcher->isEnabled = (bool) $enableNow;
        $watcher->eventResource = $eventResource;

        $this->watchers[$watcherId] = $watcher;

        event_set($eventResource, $stream, $flags, $watcher->wrapper);
        event_base_set($eventResource, $this->base);

        if ($enableNow) {
            event_add($eventResource);
        }

        return $watcherId;
    }

    private function wrapStreamCallback(LibeventWatcher $watcher): \Closure {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $stream = $watcher->stream;

        return function() use ($callback, $watcherId, $stream) {
            try {
                $result = $callback($this, $watcherId, $stream);
                if ($result instanceof \Generator) {
                    resolve($result, $this)->when($this->onCallbackResolution);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal(int $signo, callable $func): string {
        $this->enabledWatcherCount++;
        $signo = (int) $signo;
        $watcherId = (string) $this->lastWatcherId++;
        $eventResource = event_new();
        $watcher = new LibeventSignalWatcher;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::SIGNAL;
        $watcher->signo = $signo;
        $watcher->eventResource = $eventResource;
        $watcher->callback = $func;
        $watcher->isEnabled = true;

        $watcher->wrapper = $this->wrapSignalCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_set($eventResource, $signo, EV_SIGNAL | EV_PERSIST, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource);

        return $watcherId;
    }

    private function wrapSignalCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $signo = $watcher->signo;

        return function() use ($callback, $watcherId, $signo) {
            try {
                $result = $callback($this, $watcherId, $signo);
                if ($result instanceof \Generator) {
                    resolve($result, $this)->when($this->onCallbackResolution);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(string $watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        $this->enabledWatcherCount -= (int) $watcher->isEnabled;

        if (empty($watcher->eventResource)) {
            // It's an immediately watcher
            unset(
                $this->watchers[$watcherId],
                $this->immediates[$watcherId]
            );
        } else {
            event_del($watcher->eventResource);
            unset($this->watchers[$watcherId]);
        }

        $this->garbage[] = $watcher;
        $this->scheduleGarbageCollection();
    }

    /**
     * {@inheritDoc}
     */
    public function disable(string $watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        $this->enabledWatcherCount--;

        if (empty($watcher->eventResource)) {
            // It's an immediately watcher
            unset($this->immediates[$watcherId]);
            $watcher->isEnabled = false;
        } elseif ($watcher->isEnabled) {
            event_del($watcher->eventResource);
            $watcher->isEnabled = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function enable(string $watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if ($watcher->isEnabled) {
            return;
        }

        $this->enabledWatcherCount++;

        if (empty($watcher->eventResource)) {
            // It's an immediately watcher
            $this->immediates[$watcherId] = $watcher->callback;
        } elseif ($watcher->type & Watcher::TIMER) {
            event_add($watcher->eventResource, $watcher->msDelay);
        } else {
            event_add($watcher->eventResource);
        }

        $watcher->isEnabled = true;
    }

    private function scheduleGarbageCollection() {
        if (!$this->isGCScheduled) {
            event_add($this->gcEvent, 0);
            $this->isGCScheduled = true;
        }
    }

    private function collectGarbage() {
        $this->garbage = [];
        $this->isGCScheduled = false;
        event_del($this->gcEvent);
    }

    /**
     * Access the underlying libevent extension event base
     *
     * This method exists outside the base Reactor API. It provides access to the underlying
     * libevent base for code that wishes to interact with lower-level libevent extension
     * functionality.
     *
     * @return resource
     */
    public function getUnderlyingLoop() {
        return $this->base;
    }

    /**
     * {@inheritDoc}
     */
    public function onError(callable $onErrorCallback) {
        $this->onError = $onErrorCallback;
    }

    public function __destruct() {
        self::$instanceCount--;
    }

    public function __debugInfo() {
        $immediates = $timers = $readers = $writers = $signals = $disabled = 0;
        foreach ($this->watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::IMMEDIATE:
                    $immediates++;
                    break;
                case Watcher::TIMER_ONCE:
                case Watcher::TIMER_REPEAT:
                    $timers++;
                    break;
                case Watcher::IO_READER:
                    $readers++;
                    break;
                case Watcher::IO_WRITER:
                    $writers++;
                    break;
                case Watcher::SIGNAL:
                    $signals++;
                    break;
                default:
                    throw new \DomainException(
                        "Unexpected watcher type: {$watcher->type}"
                    );
            }

            $disabled += !$watcher->isEnabled;
        }

        return [
            'enabled'           => $this->enabledWatcherCount,
            'timers'            => $timers,
            'immediates'        => $immediates,
            'io_readers'        => $readers,
            'io_writers'        => $writers,
            'signals'           => $signals,
            'disabled'          => $disabled,
            'last_watcher_id'   => $this->lastWatcherId,
            'instances'         => self::$instanceCount,
        ];
    }
}

class LibeventWatcher extends Watcher {
    // Inherited from Watcher:
    // public $id;
    // public $type;
    // public $isEnabled;

    public $eventResource;
    public $callback;
    public $wrapper;
}

class LibeventSignalWatcher extends LibeventWatcher {
    public $signo;
}

class LibeventIoWatcher extends LibeventWatcher {
    public $stream;
}

class LibeventTimerWatcher extends LibeventWatcher {
    public $msDelay = -1;
}
