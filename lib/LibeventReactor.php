<?php

namespace Amp;

class LibeventReactor implements ExtensionReactor {
    private $base;
    private $watchers = [];
    private $immediates = [];
    private $lastWatcherId = "a";
    private $enabledWatcherCount = 0;
    private $resolution = 1000;
    private $isRunning = false;
    private $stopException;
    private $onError;
    private $onCoroutineResolution;

    /* Pre-PHP7 closure GC hack vars */
    private $gcEvent;
    private $garbage = [];
    private $isGcScheduled = false;

    private static $instanceCount = 0;

    public function __construct() {
        // @codeCoverageIgnoreStart
        if (!extension_loaded("libevent")) {
            throw new \RuntimeException(
                "The pecl libevent extension is required to use " . __CLASS__
            );
        }
        // @codeCoverageIgnoreEnd

        $this->base = event_base_new();

        /**
         * Prior to PHP7 we can't cancel closure watchers inside their own callbacks
         * because PHP will fatal. In legacy versions we schedule manual GC workarounds.
         *
         * @link https://bugs.php.net/bug.php?id=62452
         */
        if (PHP_MAJOR_VERSION < 7) {
            $this->gcEvent = event_new();
            event_timer_set($this->gcEvent, function() {
                $this->garbage = [];
                $this->isGcScheduled = false;
                event_del($this->gcEvent);
            });
            event_base_set($this->gcEvent, $this->base);
        }

        $this->onCoroutineResolution = function($e = null, $r = null) {
            if ($e) {
                $this->onCallbackError($e);
            }
        };

        self::$instanceCount++;
    }

    /**
     * {@inheritDoc}
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
        foreach ($immediates as $watcherId => $watcher) {
            try {
                $this->enabledWatcherCount--;
                unset(
                    $this->immediates[$watcherId],
                    $this->watchers[$watcherId]
                );
                $result = \call_user_func($watcher->callback, $this, $watcherId, $watcher->callbackData);
                if ($result instanceof \Generator) {
                    Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
                }
            } catch (\Throwable $e) {
                // @TODO Remove coverage ignore block once PHP5 support is no longer required
                // @codeCoverageIgnoreStart
                $this->onCallbackError($e);
                // @codeCoverageIgnoreEnd
            } catch (\Exception $e) {
                // @TODO Remove this catch block once PHP5 support is no longer required
                $this->onCallbackError($e);
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
        $noWait = (bool) $noWait;
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
    public function immediately(callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::IMMEDIATE, $callback, $options);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            $this->immediates[$watcher->id] = $watcher;
        }

        return $watcher->id;
    }

    private function initWatcher($type, $callback, $options) {
        $watcherId = $this->lastWatcherId++;
        $this->watchers[$watcherId] = $watcher = new \StdClass;
        $watcher->id = $watcherId;
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        if ($type !== Watcher::IMMEDIATE) {
            $watcher->eventResource = event_new();
        }

        return $watcher;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        assert(($msDelay >= 0), "\$msDelay at Argument 2 expects integer >= 0");
        $watcher = $this->initWatcher(Watcher::TIMER_ONCE, $callback, $options);
        $watcher->msDelay = ($msDelay * $this->resolution);
        event_timer_set($watcher->eventResource, $this->wrapCallback($watcher));
        event_base_set($watcher->eventResource, $this->base);

        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            event_add($watcher->eventResource, $watcher->msDelay);
        }

        return $watcher->id;
    }

    private function wrapCallback($watcher) {
        return function() use ($watcher) {
            try {
                switch ($watcher->type) {
                    case Watcher::IO_READER:
                        // fallthrough
                    case Watcher::IO_WRITER:
                        $result = \call_user_func($watcher->callback, $this, $watcher->id, $watcher->stream, $watcher->callbackData);
                        break;
                    case Watcher::TIMER_ONCE:
                        $result = \call_user_func($watcher->callback, $this, $watcher->id, $watcher->callbackData);
                        $this->cancel($watcher->id);
                        break;
                    case Watcher::TIMER_REPEAT:
                        $result = \call_user_func($watcher->callback, $this, $watcher->id, $watcher->callbackData);
                        // If the watcher cancelled itself this will no longer exist
                        if (isset($this->watchers[$watcher->id])) {
                            event_add($watcher->eventResource, $watcher->msInterval);
                        }
                        break;
                    case Watcher::SIGNAL:
                        $result = \call_user_func($watcher->callback, $this, $watcher->id, $watcher->signo, $watcher->callbackData);
                        break;
                }
                if ($result instanceof \Generator) {
                    Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
                }
            } catch (\Throwable $e) {
                // @TODO Remove coverage ignore block once PHP5 support is no longer required
                // @codeCoverageIgnoreStart
                $this->onCallbackError($e);
                // @codeCoverageIgnoreEnd
            } catch (\Exception $e) {
                // @TODO Remove this catch block once PHP5 support is no longer required
                $this->onCallbackError($e);
            }
        };
    }

    /**
     *@TODO Add a \Throwable typehint once PHP5 is no longer required
     */
    private function onCallbackError($e) {
        if (empty($this->onError)) {
            $this->stopException = $e;
            $this->stop();
        } else {
            $this->tryUserErrorCallback($e);
        }
    }

    /**
     *@TODO Add a \Throwable typehint once PHP5 is no longer required
     */
    private function tryUserErrorCallback($e) {
        try {
            \call_user_func($this->onError, $e);
        } catch (\Throwable $e) {
            // @TODO Remove coverage ignore block once PHP5 support is no longer required
            // @codeCoverageIgnoreStart
            $this->stopException = $e;
            $this->stop();
            // @codeCoverageIgnoreEnd
        } catch (\Exception $e) {
            // @TODO Remove this catch block once PHP5 support is no longer required
            $this->stopException = $e;
            $this->stop();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function repeat(callable $callback, $msInterval, array $options = []) {
        assert(($msInterval >= 0), "\$msInterval at Argument 2 expects integer >= 0");
        if (isset($options["ms_delay"])) {
            $msDelay = (int) $options["ms_delay"];
            assert(($msDelay >= 0), "ms_delay option expects integer >= 0");
            $msDelay = ($msDelay * $this->resolution);
        } else {
            $msDelay = $msInterval;
        }

        $watcher = $this->initWatcher(Watcher::TIMER_REPEAT, $callback, $options);
        $watcher->msInterval = (int) $msInterval;
        event_timer_set($watcher->eventResource, $this->wrapCallback($watcher));
        event_base_set($watcher->eventResource, $this->base);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            event_add($watcher->eventResource, $msDelay);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::IO_READER, $callback, $options);
        $watcher->stream = $stream;
        $evFlags = EV_PERSIST|EV_READ;
        event_set($watcher->eventResource, $stream, $evFlags, $this->wrapCallback($watcher));
        event_base_set($watcher->eventResource, $this->base);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            event_add($watcher->eventResource);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::IO_WRITER, $callback, $options);
        $watcher->stream = $stream;
        $evFlags = EV_PERSIST|EV_WRITE;
        event_set($watcher->eventResource, $stream, $evFlags, $this->wrapCallback($watcher));
        event_base_set($watcher->eventResource, $this->base);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            event_add($watcher->eventResource);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::SIGNAL, $callback, $options);
        $watcher->signo = $signo = (int) $signo;
        $evFlags = EV_SIGNAL | EV_PERSIST;
        event_set($watcher->eventResource, $signo, $evFlags, $this->wrapCallback($watcher));
        event_base_set($watcher->eventResource, $this->base);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            event_add($watcher->eventResource);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function cancel($watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount--;
        }

        if ($watcher->type === Watcher::IMMEDIATE) {
            unset(
                $this->immediates[$watcherId],
                $this->watchers[$watcherId]
            );
        } else {
            event_del($watcher->eventResource);
            unset($this->watchers[$watcherId]);
        }

        if (PHP_MAJOR_VERSION < 7) {
            $this->garbage[] = $watcher;
            $this->scheduleGarbageCollection();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function disable($watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if (!$watcher->isEnabled) {
            return;
        }

        switch ($watcher->type) {
            case Watcher::IMMEDIATE:
                unset($this->immediates[$watcherId]);
                break;
            case Watcher::IO_READER:    // fallthrough
            case Watcher::IO_WRITER:    // fallthrough
            case Watcher::SIGNAL:       // fallthrough
            case Watcher::TIMER_ONCE:   // fallthrough
            case Watcher::TIMER_REPEAT:
                event_del($watcher->eventResource);
                break;
        }

        $watcher->isEnabled = false;
        $this->enabledWatcherCount--;
    }

    /**
     * {@inheritDoc}
     */
    public function enable($watcherId) {
        if (empty($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            return;
        }

        switch ($watcher->type) {
            case Watcher::IMMEDIATE:
                $this->immediates[$watcherId] = $watcher;
                break;
            case Watcher::TIMER_ONCE:
                event_add($watcher->eventResource, $watcher->msDelay);
                break;
            case Watcher::TIMER_REPEAT:
                event_add($watcher->eventResource, $watcher->msInterval);
                break;
            case Watcher::IO_READER: // fallthrough
            case Watcher::IO_WRITER: // fallthrough
            case Watcher::SIGNAL:
                event_add($watcher->eventResource);
                break;
        }

        $watcher->isEnabled = true;
        $this->enabledWatcherCount++;
    }

    private function scheduleGarbageCollection() {
        if (!$this->isGcScheduled) {
            event_add($this->gcEvent, 0);
            $this->isGcScheduled = true;
        }
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
    public function onError(callable $callback) {
        $this->onError = $callback;
    }

    public function __destruct() {
        self::$instanceCount--;
    }

    public function __debugInfo() {
        $immediates = $timers = $readers = $writers = $signals = $disabled = 0;
        foreach ($this->watchers as $watcher) {
            if (!$watcher->isEnabled) {
                $disabled++;
                continue;
            }

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
            }
        }

        return [
            "immediates"        => $immediates,
            "timers"            => $timers,
            "io_readers"        => $readers,
            "io_writers"        => $writers,
            "signals"           => $signals,
            "disabled"          => $disabled,
            "last_watcher_id"   => $this->lastWatcherId,
            "instances"         => self::$instanceCount,
        ];
    }
}
