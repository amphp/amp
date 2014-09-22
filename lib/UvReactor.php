<?php

namespace Alert;

class UvReactor implements SignalReactor {
    private $loop;
    private $lastWatcherId = 1;
    private $watchers;
    private $gcWatcher;
    private $gcCallback;
    private $garbage = [];
    private $isGcScheduled = false;
    private $isRunning = false;
    private $stopException;
    private $resolution = 1000;
    private $isWindows;
    private $immediates = [];
    private $onGeneratorError;
    private $resolver;

    private static $MODE_ONCE = 0;
    private static $MODE_REPEAT = 1;
    private static $MODE_STREAM = 2;
    private static $MODE_SIGNAL = 3;
    private static $MODE_IMMEDIATE = 4;

    public function __construct($newLoop = false) {
        $this->loop = $newLoop ? uv_loop_new() : uv_default_loop();
        $this->gcWatcher = uv_timer_init($this->loop);
        $this->gcCallback = function() { $this->collectGarbage(); };
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);
        $this->resolver = new Resolver($this);
        $this->onGeneratorError = function($e, $r) {
            if ($e) {
                throw $e;
            }
        };
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
        if ($onStart) {
            $this->immediately(function() use ($onStart) {
                $result = $onStart($this);
                if ($result instanceof \Generator) {
                    $this->resolver->resolve($result)->when($this->onGeneratorError);
                }
            });
        }

        while ($this->isRunning) {
            if ($this->immediates && !$this->doImmediates()) {
                break;
            }
            uv_run($this->loop, \UV::RUN_NOWAIT | \UV::RUN_ONCE);
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
            $result = $callback($this, $watcherId);
            if ($result instanceof \Generator) {
                $this->resolver->resolve($result)->when($this->onGeneratorError);
            }
            unset(
                $this->immediates[$watcherId],
                $this->watchers[$watcherId]
            );
            if (!$this->isRunning) {
                // If a watcher stops the reactor break out of the loop
                break;
            }
        }

        return $this->isRunning;
    }

    /**
     * Execute a single event loop iteration
     *
     * @throws \Exception will throw any uncaught exception encountered during the loop iteration
     * @return void
     */
    public function tick() {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;

        if (empty($this->immediates) || $this->doImmediates()) {
            uv_run($this->loop, \UV::RUN_NOWAIT | \UV::RUN_ONCE);
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
        uv_stop($this->loop);
        $this->isRunning = false;
    }

    /**
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $callback Any valid PHP callable
     * @return string Returns a unique watcher ID
     */
    public function immediately(callable $callback) {
        $watcherId = (string) $this->lastWatcherId++;
        $this->immediates[$watcherId] = $callback;

        $watcher = new \StdClass;
        $watcher->id = $watcherId;
        $watcher->mode = self::$MODE_IMMEDIATE;
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
        return $this->startTimer($callback, $msDelay, $msInterval = 0, self::$MODE_ONCE);
    }

    /**
     * Schedule a recurring callback to execute every $msInterval seconds until cancelled
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msInterval The interval in milliseconds between callback invocations
     * @return string Returns a unique watcher ID
     */
    public function repeat(callable $callback, $msInterval) {
        // A zero interval is interpreted as a "non-repeating" timer by php-uv. Here
        // we use a hack to notify on STDOUT writability for 0 interval repeating
        // callbacks because it's much more performant than churning 1ms timers.
        $msInterval = ($msInterval && $msInterval > 0) ? (int) $msInterval : -1;

        return ($msInterval === -1)
            ? $this->watchStream(STDOUT, $callback, self::WATCH_WRITE | self::WATCH_NOW)
            : $this->startTimer($callback, $msInterval, $msInterval, self::$MODE_REPEAT);
    }

    private function startTimer($callback, $msDelay, $msInterval, $mode) {
        $watcher = new UvTimerWatcher;
        $watcher->id = (string) $this->lastWatcherId++;
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
                $result = $callback($this, $watcher->id);
                if ($result instanceof \Generator) {
                    $this->resolver->resolve($result)->when($this->onGeneratorError);
                }
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
     * Watch a stream resource for IO readable data and trigger the callback when actionable
     *
     * @param resource $stream A stream resource to watch for readable data
     * @param callable $callback Any valid PHP callable
     * @param bool $enableNow Should the watcher be enabled now or held for later use?
     * @return string Returns a unique watcher ID
     */
    public function onReadable($stream, callable $callback, $enableNow = true) {
        $flags = $enableNow ? (self::WATCH_READ | self::WATCH_NOW) : self::WATCH_READ;

        return $this->watchStream($stream, $callback, $flags);
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
        $flags = $enableNow ? (self::WATCH_WRITE | self::WATCH_NOW) : self::WATCH_WRITE;

        return $this->watchStream($stream, $callback, $flags);
    }

    /**
     * Watch a stream resource for reads or writes (but not both) with additional option flags
     *
     * @param resource $stream
     * @param callable $callback
     * @param int $flags A bitmask of watch flags
     * @throws \DomainException if no read/write flag specified
     * @return string Returns a unique watcher ID
     */
    public function watchStream($stream, callable $callback, $flags) {
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

        $watcherId = (string) $this->lastWatcherId++;

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
                $result = $callback($this, $watcher->id, $watcher->stream);
                if ($result instanceof \Generator) {
                    $this->resolver->resolve($result)->when($this->onGeneratorError);
                }
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
     * @return string Returns a unique watcher ID
     */
    public function onSignal($signo, callable $onSignal) {
        $watcher = new UvSignalWatcher;
        $watcher->id = (string) $this->lastWatcherId++;
        $watcher->mode = self::$MODE_SIGNAL;
        $watcher->signo = $signo;
        $watcher->uvStruct = uv_signal_init($this->loop);
        $watcher->callback = $this->wrapSignalCallback($watcher, $onSignal);
        $watcher->isEnabled = true;

        uv_signal_start($watcher->uvStruct, $watcher->callback, $watcher->signo);

        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    private function wrapSignalCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $result = $callback($this, $watcher->id, $watcher->signo);
                if ($result instanceof \Generator) {
                    $this->resolver->resolve($result)->when($this->onGeneratorError);
                }
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
                case self::$MODE_IMMEDIATE:
                    unset($this->immediates[$watcherId]);
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
            case self::$MODE_IMMEDIATE:
                unset($this->immediates[$watcher->id]);
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
            case self::$MODE_IMMEDIATE:
                $this->immediates[$watcher->id] = $watcher->callback;
                break;
            default:
                uv_timer_start($watcher->uvStruct, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
                break;
        }

        $watcher->isEnabled = true;
    }

    /**
     * Access the underlying php-uv extension loop resource
     *
     * This method exists outside the base Reactor API. It provides access to the underlying php-uv
     * event loop resource for code that wishes to interact with lower-level php-uv extension
     * functionality.
     *
     * @return resource
     */
    public function getUnderlyingLoop() {
        return $this->loop;
    }
}
