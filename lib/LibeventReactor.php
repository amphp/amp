<?php

namespace Alert;

class LibeventReactor implements SignalReactor {
    private $base;
    private $watchers = [];
    private $lastWatcherId = 0;
    private $resolution = 1000;
    private $isRunning = false;
    private $isGCScheduled = false;
    private $garbage = [];
    private $gcEvent;
    private $stopException;

    public function __construct() {
        $this->base = event_base_new();
        $this->gcEvent = event_new();
        event_timer_set($this->gcEvent, [$this, 'collectGarbage']);
        event_base_set($this->gcEvent, $this->base);
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

        if ($onStart) {
            $this->immediately(function() use ($onStart) { $onStart($this); });
        }

        $this->doRun();
    }

    /**
     * Execute a single event loop iteration
     *
     * @throws \Exception will throw any uncaught exception encountered during the loop iteration
     * @return void
     */
    public function tick() {
        if (!$this->isRunning) {
            $this->doRun(EVLOOP_ONCE | EVLOOP_NONBLOCK);
        }
    }

    private function doRun($flags = 0) {
        $this->isRunning = true;
        event_base_loop($this->base, $flags);
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
    }

    /**
     * Schedule an event to trigger once at the specified time
     *
     * @param callable $callback Any valid PHP callable
     * @param string $timeString Any string that can be parsed by strtotime() and is in the future
     * @throws \InvalidArgumentException if $timeString parse fails
     * @return int Returns a unique integer watcher ID
     */
    public function at(callable $callback, $timeString) {
        $now = time();
        $executeAt = @strtotime($timeString);

        if ($executeAt === false || $executeAt <= $now) {
            throw new \InvalidArgumentException(
                'Valid future time string (parsable by strtotime()) required'
            );
        }

        $msDelay = ($executeAt - $now) * $this->resolution;

        return $this->once($callback, $msDelay);
    }

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $callback Any valid PHP callable
     * @return int Returns a unique integer watcher ID
     */
    public function immediately(callable $callback) {
        return $this->once($callback, $msDelay = 0);
    }

    /**
     * Schedule a callback to execute once
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
     * @return int Returns a unique integer watcher ID
     */
    public function once(callable $callback, $msDelay) {
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapOnceCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $msDelay);

        return $watcherId;
    }

    private function wrapOnceCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;

        return function() use ($callback, $watcherId) {
            try {
                $callback($watcherId, $this);
                $this->cancel($watcherId);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The interval in milliseconds between callback invocations
     * @return int Returns a unique integer watcher ID
     */
    public function repeat(callable $callback, $msDelay) {
        $watcherId = $this->lastWatcherId++;
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapRepeatingCallback($watcher);

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
                $callback($watcherId, $this);
                event_add($eventResource, $msDelay);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    /**
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return int Returns a unique integer watcher ID
     */
    public function onReadable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, EV_READ | EV_PERSIST, $callback, $enableNow);
    }

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for writability
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return int Returns a unique integer watcher ID
     */
    public function onWritable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, EV_WRITE | EV_PERSIST, $callback, $enableNow);
    }

    private function watchIoStream($stream, $flags, callable $callback, $enableNow) {
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
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

        return function($stream) use ($callback, $watcherId) {
            try {
                $callback($watcherId, $stream, $this);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    /**
     * Watch a stream resource for reads or writes (but not both) with additional option flags
     *
     * @param resource $stream
     * @param callable $callback
     * @param int $flags A bitmask of watch flags
     * @throws \DomainException if no read/write flag specified
     * @return int Returns a unique integer watcher ID
     */
    public function watchStream($stream, callable $callback, $flags) {
        $flags = (int) $flags;
        $enableNow = ($flags & self::WATCH_NOW);

        if ($flags & self::WATCH_READ) {
            return $this->onWritable($stream, $callback, $enableNow);
        } elseif ($flags & self::WATCH_WRITE) {
            return $this->onWritable($stream, $callback, $enableNow);
        } else {
            throw new \DomainException(
                'Stream watchers must specify either a WATCH_READ or WATCH_WRITE flag'
            );
        }
    }

    /**
     * React to process control signals
     *
     * @param int $signo The signal number to watch for (e.g. 2 for Uv::SIGINT)
     * @param callable $onSignal
     * @return int Returns a unique integer watcher ID
     */
    public function onSignal($signo, callable $onSignal) {
        $signo = (int) $signo;
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();
        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->callback = $onSignal;

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

        return function() use ($callback, $watcherId) {
            try {
                $callback($watcherId, $this);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
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
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        event_del($watcher->eventResource);
        $this->garbage[] = $watcher;
        $this->scheduleGarbageCollection();
        unset($this->watchers[$watcherId]);
    }

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param int $watcherId
     * @return void
     */
    public function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
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
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if (!$watcher->isEnabled) {
            event_add($watcher->eventResource, $watcher->msDelay);
            $watcher->isEnabled = true;
        }
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

}
