<?php

namespace Amp;

class LibeventReactor extends CoroutineResolver implements SignalReactor {
    private $base;
    private $watchers = [];
    private $immediates = [];
    private $lastWatcherId = 1;
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
     * Start the event reactor and assume program flow control
     *
     * @param callable $onStart Optional callback to invoke immediately upon reactor start
     * @throws \Exception Will throw if code executed during the event loop throws
     * @return void
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
                    $this->coroutine($result)->when($this->onCallbackResolution);
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
     * Execute a single event loop iteration
     *
     * @param bool $noWait If TRUE, return immediately when no watchers are immediately ready to trigger
     * @return void
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
     * Stop the event reactor
     *
     * @return void
     */
    public function stop() {
        event_base_loopexit($this->base);
        $this->isRunning = false;
    }

    /**
     * Schedule an event to trigger once at the specified time
     *
     * @param callable $callback Any valid PHP callable
     * @param mixed[int|string] $unixTimeOrStr A future unix timestamp or string parsable by strtotime()
     * @throws \InvalidArgumentException On invalid future time
     * @return string Returns a unique watcher ID
     */
    public function at(callable $callback, $unixTimeOrStr) {
        $now = time();
        if (is_int($unixTimeOrStr) && $unixTimeOrStr > $now) {
            $secondsUntil = ($unixTimeOrStr - $now);
        } elseif (($executeAt = @strtotime($unixTimeOrStr)) && $executeAt > $now) {
            $secondsUntil = ($executeAt - $now);
        } else {
            throw new \InvalidArgumentException(
                'Unix timestamp or future time string (parsable by strtotime()) required'
            );
        }

        $msDelay = $secondsUntil * $this->resolution;

        return $this->once($callback, $msDelay);
    }

    /**
     * Schedule a callback for immediate invocation in the next event loop tick
     *
     * @param callable $callback Any valid PHP callable
     * @return string Returns a unique watcher ID
     */
    public function immediately(callable $callback) {
        $this->enabledWatcherCount++;
        $watcherId = (string) $this->lastWatcherId++;
        $this->immediates[$watcherId] = $callback;

        $watcher = new \StdClass;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::IMMEDIATE;
        $watcher->callback = $callback;
        $watcher->isEnabled = true;

        $this->watchers[$watcher->id] = $watcher;

        return $watcherId;
    }

    /**
     * Schedule a callback to execute once
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
     * @return string Returns a unique watcher ID
     */
    public function once(callable $callback, $msDelay) {
        $this->enabledWatcherCount++;
        $watcherId = (string) $this->lastWatcherId++;
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
                    $this->coroutine($result)->when($this->onCallbackResolution);
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
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The interval in milliseconds between callback invocations
     * @return string Returns a unique watcher ID
     */
    public function repeat(callable $callback, $msDelay) {
        $this->enabledWatcherCount++;
        $watcherId = (string) $this->lastWatcherId++;
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

    private function wrapRepeatingCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $eventResource = $watcher->eventResource;
        $msDelay = $watcher->msDelay;

        return function() use ($callback, $eventResource, $msDelay, $watcherId) {
            try {
                $result = $callback($this, $watcherId);
                if ($result instanceof \Generator) {
                    $this->coroutine($result)->when($this->onCallbackResolution);
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
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return string Returns a unique watcher ID
     */
    public function onReadable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, Watcher::IO_READER, $callback, $enableNow);
    }

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for writability
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return string Returns a unique watcher ID
     */
    public function onWritable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, Watcher::IO_WRITER, $callback, $enableNow);
    }

    private function watchIoStream($stream, $type, callable $callback, $enableNow) {
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

    private function wrapStreamCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $stream = $watcher->stream;

        return function() use ($callback, $watcherId, $stream) {
            try {
                $result = $callback($this, $watcherId, $stream);
                if ($result instanceof \Generator) {
                    $this->coroutine($result)->when($this->onCallbackResolution);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    /**
     * React to process control signals
     *
     * @param int $signo The signal number to watch for (e.g. 2 for Uv::SIGINT)
     * @param callable $onSignal
     * @return string Returns a unique watcher ID
     */
    public function onSignal($signo, callable $onSignal) {
        $this->enabledWatcherCount++;
        $signo = (int) $signo;
        $watcherId = (string) $this->lastWatcherId++;
        $eventResource = event_new();
        $watcher = new LibeventSignalWatcher;
        $watcher->id = $watcherId;
        $watcher->type = Watcher::SIGNAL;
        $watcher->signo = $signo;
        $watcher->eventResource = $eventResource;
        $watcher->callback = $onSignal;
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
                    $this->coroutine($result)->when($this->onCallbackResolution);
                }
            } catch (\Exception $e) {
                $this->handleRunError($e);
            }
        };
    }

    /**
     * Cancel an existing watcher
     *
     * @param int $watcherId
     * @return void
     */
    public function cancel($watcherId) {
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
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param int $watcherId
     * @return void
     */
    public function disable($watcherId) {
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
     * Enable a disabled timer/stream watcher
     *
     * @param int $watcherId
     * @return void
     */
    public function enable($watcherId) {
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
     * An optional "last-chance" exception handler for errors resulting during callback invocation
     *
     * If a reactor callback throws and no onError() callback is specified the exception will
     * bubble up the stack. onError() callbacks are passed a single parameter: the uncaught
     * exception that resulted in the callback's invocation.
     *
     * @param callable $onErrorCallback
     * @return self
     */
    public function onError(callable $onErrorCallback) {
        $this->onError = $onErrorCallback;

        return $this;
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
