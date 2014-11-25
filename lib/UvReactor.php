<?php

namespace Amp;

class UvReactor implements SignalReactor {
    private $loop;
    private $lastWatcherId = 1;
    private $watchers;
    private $streamIdPollMap = [];
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

    private static $MODE_ONCE = 0;
    private static $MODE_REPEAT = 1;
    private static $MODE_READER = 2;
    private static $MODE_WRITER = 3;
    private static $MODE_SIGNAL = 4;
    private static $MODE_IMMEDIATE = 5;

    public function __construct($newLoop = false) {
        $this->loop = $newLoop ? uv_loop_new() : uv_default_loop();
        $this->gcWatcher = uv_timer_init($this->loop);
        $this->gcCallback = function() { $this->collectGarbage(); };
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);
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
            $this->immediately($onStart);
        }

        while ($this->isRunning) {
            if ($this->immediates && !$this->doImmediates()) {
                break;
            }
            uv_run($this->loop, \UV::RUN_ONCE);
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
                resolve($this, $result)->when($this->onGeneratorError);
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
            ? $this->watchStream(STDOUT, $callback, self::$MODE_WRITER, true)
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
                    resolve($this, $result)->when($this->onGeneratorError);
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
        return $this->watchStream($stream, $callback, self::$MODE_READER, (bool) $enableNow);
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
        return $this->watchStream($stream, $callback, self::$MODE_WRITER, (bool) $enableNow);
    }

    private function watchStream($stream, callable $callback, $mode, $enableNow) {
        $streamId = (int) $stream;
        $poll = isset($this->streamIdPollMap[$streamId])
            ? $this->streamIdPollMap[$streamId]
            : $this->makePollHandle($stream);

        $watcherId = $this->lastWatcherId;
        $this->watchers[$watcherId] = $watcher = new UvIoWatcher;
        $watcher->id = $watcherId = $this->lastWatcherId++;
        $watcher->mode = $mode;
        $watcher->poll = $poll;
        $watcher->stream = $stream;
        $watcher->callback = $callback;
        $watcher->isEnabled = $enableNow;

        if ($enableNow === false) {
            $poll->disable[$watcherId] = $watcher;
            return $watcherId;
        }

        if ($mode === self::$MODE_READER) {
            $poll->readers[$watcherId] = $watcher;
        } else {
            $poll->writers[$watcherId] = $watcher;
        }

        $newFlags = 0;
        if ($poll->readers) {
            $newFlags |= \UV::READABLE;
        }
        if ($poll->writers) {
            $newFlags |= \UV::WRITABLE;
        }
        if ($newFlags != $poll->flags) {
            $poll->flags = $newFlags;
            uv_poll_start($poll->handle, $newFlags, $poll->callback);
        }

        return $watcherId;
    }

    private function makePollHandle($stream) {
        // Windows needs the socket-specific init function, so make sure we use
        // it when dealing with tcp/ssl streams.
        $pollInitFunc = $this->isWindows
            ? $this->chooseWindowsPollingFunction($stream)
            : 'uv_poll_init';

        $streamId = (int) $stream;
        $this->streamIdPollMap[$streamId] = $poll = new UvPoll;
        $poll->flags = 0;
        $poll->handle = $pollInitFunc($this->loop, $stream);
        $poll->callback = function($uvHandle, $stat, $events) use ($poll) {
            if ($events & \UV::READABLE) {
                foreach ($poll->readers as $watcher) {
                    $this->invokePollWatcher($watcher);
                }
            }
            if ($events & \UV::WRITABLE) {
                foreach ($poll->writers as $watcher) {
                    $this->invokePollWatcher($watcher);
                }
            }
        };

        return $poll;
    }

    private function chooseWindowsPollingFunction($stream) {
        $streamType = stream_get_meta_data($stream)['stream_type'];

        return ($streamType === 'tcp_socket/ssl' || $streamType === 'tcp_socket')
            ? 'uv_poll_init_socket'
            : 'uv_poll_init';
    }

    private function invokePollWatcher(UvIoWatcher $watcher) {
        try {
            $callback = $watcher->callback;
            $result = $callback($this, $watcher->id, $watcher->stream);
            if ($result instanceof \Generator) {
                resolve($this, $result)->when($this->onGeneratorError);
            }
        } catch (\Exception $e) {
            $this->stopException = $e;
            $this->stop();
        }
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
                    resolve($this, $result)->when($this->onGeneratorError);
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
                case self::$MODE_READER:
                    // fallthrough
                case self::$MODE_WRITER:
                    $this->clearPollFromWatcher($watcher);
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

    private function clearPollFromWatcher(UvIoWatcher $watcher) {
        $poll = $watcher->poll;
        $watcherId = $watcher->id;

        unset(
            $poll->readers[$watcherId],
            $poll->writers[$watcherId],
            $poll->disable[$watcherId]
        );

        // If any watchers are still enabled for this stream we're finished here
        $hasEnabledWatchers = ((int) $poll->readers) + ((int) $poll->writers);
        if ($hasEnabledWatchers) {
            return;
        }

        // Always stop polling if no enabled watchers remain
        uv_poll_stop($poll->handle);

        // If all watchers are disabled we can pull out here
        $hasDisabledWatchers = (bool) $poll->disable;
        if ($hasDisabledWatchers) {
            return;
        }

        // Otherwise there are no watchers left for this poll and we should clear it
        $streamId = (int) $watcher->stream;
        unset($this->streamIdPollMap[$streamId]);
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
            case self::$MODE_READER:
                // fallthrough
            case self::$MODE_WRITER:
                $this->disablePollFromWatcher($watcher);
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

    private function disablePollFromWatcher(UvIoWatcher $watcher) {
        $poll = $watcher->poll;
        $watcherId = $watcher->id;

        unset(
            $poll->readers[$watcherId],
            $poll->writers[$watcherId]
        );

        $poll->disable[$watcherId] = $watcher;

        if (!($poll->readers || $poll->writers)) {
            uv_poll_stop($poll->handle);
            return;
        }

        // If we're still here we may need to update the polling flags
        $newFlags = 0;
        if ($poll->readers) {
            $newFlags |= \UV::READABLE;
        }
        if ($poll->writers) {
            $newFlags |= \UV::WRITABLE;
        }
        if ($poll->flags != $newFlags) {
            $poll->flags = $newFlags;
            uv_poll_start($poll->handle, $newFlags, $poll->callback);
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

        if ($watcher->isEnabled) {
            return;
        }

        switch ($watcher->mode) {
            case self::$MODE_READER:
                // fallthrough
            case self::$MODE_WRITER:
                $this->enablePollFromWatcher($watcher);
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

    private function enablePollFromWatcher(UvIoWatcher $watcher) {
        $poll = $watcher->poll;
        $watcherId = $watcher->id;

        unset($poll->disable[$watcherId]);

        $preexistingFlags = $poll->flags;

        if ($watcher->mode === self::$MODE_READER) {
            $poll->flags |= \UV::READABLE;
            $poll->readers[$watcherId] = $watcher;
        } else {
            $poll->flags |= \UV::WRITABLE;
            $poll->writers[$watcherId] = $watcher;
        }

        if ($preexistingFlags != $poll->flags) {
            uv_poll_start($poll->handle, $poll->flags, $poll->callback);
        }
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
