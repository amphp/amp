<?php

namespace Alert;

class UvReactor implements SignalReactor {
    private $loop;
    private $lastWatcherId = 0;
    private $watchers;
    private $gcWatcher;
    private $gcCallback;
    private $garbage = [];
    private $isGcScheduled = false;
    private $isRunning = false;
    private $stopException;
    private $resolution = 1000;
    private $isWindows;

    private static $MODE_ONCE = 0;
    private static $MODE_REPEAT = 1;
    private static $MODE_STREAM = 2;
    private static $MODE_SIGNAL = 3;

    public function __construct($newLoop = false) {
        $this->loop = $newLoop ? uv_loop_new() : uv_default_loop();
        $this->gcWatcher = uv_timer_init($this->loop);
        $this->gcCallback = function() { $this->collectGarbage(); };
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);
    }

    private function collectGarbage() {
        $this->garbage = [];
        $this->isGcScheduled = false;
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
        $this->immediately(function() use ($onStart) { $onStart($this); });
        uv_run($this->loop);
        $this->isRunning = false;

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    /**
     * Execute a single event loop iteration
     */
    public function tick() {
        $this->isRunning = true;
        uv_run_once($this->loop);
        $this->isRunning = false;

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
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
        $watcher->isEnabled = true;

        $this->watchers[$watcher->id] = $watcher;

        uv_timer_start($watcher->uvStruct, $watcher->msDelay, $watcher->msInterval, $watcher->callback);

        return $watcher->id;
    }

    private function wrapTimerCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $callback($watcher->id, $this);
                if ($watcher->mode === self::$MODE_ONCE) {
                    $this->clearWatcher($watcher->id);
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
     * @throws \InvalidArgumentException if $timeString parse fails
     * @return int
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
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return int Returns a unique integer watcher ID
     */
    public function onReadable($stream, callable $callback, $enableNow = true) {
        $flags = $enableNow ? (self::WATCH_READ | self::WATCH_NOW) : self::WATCH_READ;

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
    public function onWritable($stream, callable $callback, $enableNow = true) {
        $flags = $enableNow ? (self::WATCH_WRITE | self::WATCH_NOW) : self::WATCH_WRITE;

        return $this->watchStream($stream, $flags, $callback);
    }

    /**
     * Watch a stream resource for reads or writes (but not both) with additional option flags
     *
     * @param resource $stream
     * @param int $flags A bitmask of watch flags
     * @param callable $callback
     * @throws \DomainException if no read/write flag specified
     * @return int Returns a unique integer watcher ID
     */
    public function watchStream($stream, $flags, callable $callback) {
        $flags = (int) $flags;

        if ($flags & self::WATCH_READ) {
            /** @noinspection PhpUndefinedClassInspection */
            $pollFlag = \UV::READABLE;
        } elseif ($flags & self::WATCH_WRITE) {
            /** @noinspection PhpUndefinedClassInspection */
            $pollFlag = \UV::WRITABLE;
        } else {
            throw new \DomainException(
                'Stream watchers must specify either a WATCH_READ or WATCH_WRITE flag'
            );
        }

        // Windows requires the socket-specific init function, so make sure we choose that
        // specifically when using tcp/ssl streams
        $pollStartFunc = $this->isWindows
            ? $this->chooseWindowsPollingFunction($stream)
            : 'uv_poll_init';

        $watcherId = $this->lastWatcherId++;

        $watcher = new UvIoWatcher;
        $watcher->id = $watcherId;
        $watcher->mode = self::$MODE_STREAM;
        $watcher->stream = $stream;
        $watcher->pollFlag = $pollFlag;
        $watcher->uvStruct = $pollStartFunc($this->loop, $stream);
        $watcher->callback = $this->wrapStreamCallback($watcher, $callback);
        if ($watcher->isEnabled = ($flags & self::WATCH_NOW)) {
            uv_poll_start($watcher->uvStruct, $watcher->pollFlag, $watcher->callback);
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    private function chooseWindowsPollingFunction($stream) {
        return (stream_get_meta_data($stream)['stream_type'] === 'tcp_socket/ssl')
            ? 'uv_poll_init_socket'
            : 'uv_poll_init';
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
     * React to process control signals
     *
     * @param int $signo The signal number to watch for (e.g. 2 for Uv::SIGINT)
     * @param callable $onSignal
     * @return int Returns a unique integer watcher ID
     */
    public function onSignal($signo, callable $onSignal) {
        $watcher = new UvSignalWatcher;
        $watcher->id = $this->lastWatcherId++;
        $watcher->mode = self::$MODE_SIGNAL;
        $watcher->signo = $signo;
        $watcher->uvStruct = uv_signal_init($this->loop);
        $watcher->callback = $this->wrapSignalCallback($watcher, $onSignal);
        $watcher->isEnabled = true;

        uv_signal_start($watcher->uvStruct, $watcher->uvStruct, $watcher->signo);

        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    private function wrapSignalCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $callback($watcher->id, $watcher->signo, $this);
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
        if (isset($this->watchers[$watcherId])) {
            $this->clearWatcher($watcherId);
        }
    }

    private function clearWatcher($watcherId) {
        $watcher = $this->watchers[$watcherId];
        unset($this->watchers[$watcherId]);

        if ($watcher->isEnabled) {
            switch ($watcher->mode) {
                case self::$MODE_STREAM:
                    uv_poll_stop($watcher->uvStruct);
                    break;
                case self::$MODE_SIGNAL:
                    uv_signal_stop($watcher->uvStruct);
                    break;
                default:
                    uv_timer_stop($watcher->uvStruct);
                    break;
            }
        }

        $this->garbage[] = $watcher;

        if (!$this->isGcScheduled) {
            uv_timer_start($this->gcWatcher, 250, 0, $this->gcCallback);
            $this->isGcScheduled = true;
        }
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

        if (!$watcher->isEnabled) {
            return;
        }

        switch ($watcher->mode) {
            case self::$MODE_STREAM:
                uv_poll_stop($watcher->uvStruct);
                break;
            case self::$MODE_SIGNAL:
                uv_signal_stop($watcher->uvStruct);
                break;
            default:
                uv_timer_stop($watcher->uvStruct);
                break;
        }

        $watcher->isEnabled = false;
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

        if ($watcher->isEnabled) {
            return;
        }

        switch ($watcher->mode) {
            case self::$MODE_STREAM:
                uv_poll_start($watcher->uvStruct, $watcher->pollFlag, $watcher->callback);
                break;
            case self::$MODE_SIGNAL:
                uv_signal_start($watcher->uvStruct, $watcher->callback, $watcher->signo);
                break;
            default:
                uv_timer_start($watcher->uvStruct, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
                break;
        }

        $watcher->isEnabled = true;
    }
}
