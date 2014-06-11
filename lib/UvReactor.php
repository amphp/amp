<?php

namespace Alert;

class UvReactor implements Reactor {
    private $loop;
    private $lastWatcherId = 0;
    private $gcWatcher;
    private $gcCallback;
    private $garbage = [];
    private $isGcScheduled = FALSE;
    private $isRunning = FALSE;
    private $stopException;
    private $resolution = 1000;

    private static $MODE_ONCE = 0;
    private static $MODE_REPEAT = 1;
    private static $MODE_STREAM = 2;

    public function __construct($newLoop = FALSE) {
        $this->loop = $newLoop ? uv_loop_new() : uv_default_loop();
        $this->gcWatcher = uv_timer_init($this->loop);
        $this->gcCallback = function() { $this->collectGarbage(); };
    }

    private function collectGarbage() {
        $this->garbage = [];
        $this->isGcScheduled = FALSE;
    }

    /**
     * Start the event reactor and assume program flow control
     *
     * @param $onStart Optional callback to invoke immediately upon reactor start
     */
    public function run(callable $onStart = NULL) {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = TRUE;
        uv_run($this->loop);
        $this->isRunning = FALSE;

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = NULL;
            throw $e;
        }
    }

    /**
     * Execute a single event loop iteration
     */
    public function tick() {
        $this->isRunning = TRUE;
        uv_run_once($this->loop);
        $this->isRunning = FALSE;

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = NULL;
            throw $e;
        }
    }

    /**
     * Stop the event reactor
     */
    public function stop() {
        uv_stop($this->loop);
    }

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $callback Any valid PHP callable
     * @return int Returns a unique integer watcher ID
     */
    public function immediately(callable $callback) {
        return $this->startTimer($callback, $msDelay = 0, $msInterval = 0, self::$MODE_ONCE);
    }

    /**
     * Schedule a callback to execute once
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
     * @return int Returns a unique integer watcher ID
     */
    public function once(callable $callback, $msDelay) {
        return $this->startTimer($callback, $msDelay, $msInterval = 0, self::$MODE_ONCE);
    }

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msInterval The interval in milliseconds between callback invocations
     * @return int Returns a unique integer watcher ID
     */
    public function repeat(callable $callback, $msInterval) {
        // A zero interval is interpreted as a "non-repeating" timer by php-uv; use 1ms instead.
        $msInterval = ($msInterval && $msInterval > 0) ? (int) $msInterval : 1;

        return $this->startTimer($callback, $msInterval, $msInterval, self::$MODE_REPEAT);
    }

    private function startTimer($callback, $msDelay, $msInterval, $mode) {
        $watcher = new UvTimerWatcher;
        $watcher->id = $this->lastWatcherId++;
        $watcher->mode = $mode;
        $watcher->uvStruct = uv_timer_init($this->loop);
        $watcher->callback = $this->wrapTimerCallback($watcher, $callback);
        $watcher->msDelay = ($msDelay > 0) ? (int) $msDelay : 0;
        $watcher->msInterval = ($msInterval > 0) ? (int) $msInterval : 0;
        $watcher->isEnabled = TRUE;

        $this->watchers[$watcher->id] = $watcher;

        uv_timer_start($watcher->uvStruct, $watcher->msDelay, $watcher->msInterval, $watcher->callback);

        return $watcher->id;
    }

    private function wrapTimerCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $callback($watcher->id, $this);
                if ($watcher->mode === self::$MODE_ONCE) {
                    $this->clearWatcher($watcherId);
                }
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    /**
     * Schedule an event to trigger once at the specified time
     *
     * @param callable $callback Any valid PHP callable
     * @param string $timeString Any string that can be parsed by strtotime() and is in the future
     * @TODO Implement me.
     */
    public function at(callable $callback, $timeString) {
        $now = time();
        $executeAt = @strtotime($timeString);

        if ($executeAt === FALSE || $executeAt <= $now) {
            throw new \InvalidArgumentException(
                'Valid future time string (parsable by strtotime()) required'
            );
        }

        $msDelay = ($executeAt - $now) * $this->resolution;

        return $this->once($callback, $msDelay);
    }

    /**
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return int Returns a unique integer watcher ID
     */
    public function onReadable($stream, callable $callback, $enableNow = TRUE) {
        $flags = $enableNow ? (self::POLL_READ | self::ENABLE_NOW) : SELF::POLL_READ;

        return $this->watchStream($stream, $flags, $callback);
    }

    /**
     * Watch a stream resource to become writable and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for writability
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return int Returns a unique integer watcher ID
     */
    public function onWritable($stream, callable $callback, $enableNow = TRUE) {
        $flags = $enableNow ? (self::POLL_WRITE | self::ENABLE_NOW) : SELF::POLL_WRITE;

        return $this->watchStream($stream, $flags, $callback);
    }

    /**
     * Watch a stream resource for reads or writes (but not both) with additional option flags
     *
     * NOTE: Windows users MUST specify the Reactor::POLL_SOCK flag when watching a socket
     * stream or ext/sockets resource -- this is a limitation of the underlying C code.
     *
     * @param resource $stream
     * @param int $flags A bitmask of watch flags
     * @param callable $callback
     */
    public function watchStream($stream, $flags, callable $callback) {
        $flags = (int) $flags;

        if ($flags & self::POLL_READ) {
            $pollFlag = \UV::READABLE;
        } elseif ($flags & self::POLL_WRITE) {
            $pollFlag = \UV::WRITABLE;
        } else {
            throw new \DomainException(
                'Stream watchers must specify either a POLL_READ or POLL_WRITE flag'
            );
        }

        // Windows requires the socket-specific init function, so we use the POLL_SOCK flag to
        // maximize cross-OS compatibility when polling sockets. This one-off option is a major
        // reason for the existence of Reactor::watchStream() because we need an easy way to
        // specify flags that may not be applicable across all reactor implementations without
        // simultaneously fractaling out the interface API.
        $pollStartFunc = ($flags & self::POLL_SOCK) ? 'uv_poll_init_socket' : 'uv_poll_init';

        $watcherId = $this->lastWatcherId++;

        $watcher = new UvIoWatcher;
        $watcher->id = $watcherId;
        $watcher->mode = self::$MODE_STREAM;
        $watcher->stream = $stream;
        $watcher->pollFlag = $pollFlag;
        $watcher->uvStruct = $pollStartFunc($this->loop, $stream);
        $watcher->callback = $this->wrapStreamCallback($watcher, $callback);
        if ($watcher->isEnabled = ($flags & self::ENABLE_NOW)) {
            uv_poll_start($watcher->uvStruct, $watcher->pollFlag, $watcher->callback);
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    private function wrapStreamCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $callback($watcher->id, $watcher->stream, $this);
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
     */
    public function cancel($watcherId) {
        if (isset($this->watchers[$watcherId])) {
            $this->clearWatcher($watcherId);
        }
    }

    private function clearWatcher($watcherId) {
        $watcher = $this->watchers[$watcherId];
        unset($this->watchers[$watcherId]);

        if ($watcher->isEnabled) {
            $stopFunc = ($watcher instanceof UvIoWatcher) ? 'uv_poll_stop' : 'uv_timer_stop';
            $stopFunc($watcher->uvStruct);
        }

        $this->garbage[] = $watcher;

        if (!$this->isGcScheduled) {
            uv_timer_start($this->gcWatcher, 250, 0, $this->gcCallback);
            $this->isGcScheduled = TRUE;
        }
    }

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param int $watcherId
     */
    public function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if ($watcher->isEnabled) {
            $stopFunc = ($watcher instanceof UvIoWatcher) ? 'uv_poll_stop' : 'uv_timer_stop';
            $stopFunc($watcher->uvStruct);
            $watcher->isEnabled = FALSE;
        }
    }

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param int $watcherId
     */
    public function enable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if ($watcher->isEnabled) {
            return;
        }

        if ($watcher->mode === self::$MODE_STREAM) {
            uv_poll_start($watcher->uvStruct, $watcher->pollFlag, $watcher->callback);
        } else {
            uv_timer_start($watcher->uvStruct, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
        }

        $watcher->isEnabled = TRUE;
    }
}
