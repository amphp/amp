<?php

namespace Amp;

class UvReactor extends CoroutineResolver implements SignalReactor {
    private $loop;
    private $lastWatcherId = 1;
    private $watchers;
    private $enabledWatcherCount = 0;
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
    private $onError;
    private $onCallbackResolution;

    private static $instanceCount = 0;

    public function __construct() {
        if (!extension_loaded('uv')) {
            throw new \RuntimeException('The php-uv extension is required to use the UvReactor.');
        }

        $this->loop = uv_loop_new();
        $this->gcWatcher = uv_timer_init($this->loop);
        $this->gcCallback = function() { $this->collectGarbage(); };
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);
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
            if (empty($this->enabledWatcherCount)) {
                break;
            }
            uv_run($this->loop, \UV::RUN_DEFAULT | (empty($this->immediates) ? \UV::RUN_ONCE : \UV::RUN_NOWAIT));
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
                // If a watcher stops the reactor break out of the loop
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
            $flags = $noWait || !empty($this->immediates) ? (\UV::RUN_NOWAIT | \UV::RUN_ONCE) : \UV::RUN_ONCE;
            uv_run($this->loop, $flags);
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
        return $this->startTimer($callback, $msDelay, $msInterval = 0, Watcher::TIMER_ONCE);
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
            ? $this->watchStream(STDOUT, $callback, Watcher::IO_WRITER, true)
            : $this->startTimer($callback, $msInterval, $msInterval, Watcher::TIMER_REPEAT);
    }

    private function startTimer($callback, $msDelay, $msInterval, $type) {
        $this->enabledWatcherCount++;
        $watcher = new UvTimerWatcher;
        $watcher->id = (string) $this->lastWatcherId++;
        $watcher->type = $type;
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
                    $this->coroutine($result)->when($this->onCallbackResolution);
                }
                // The isset() check is necessary because the "once" timer
                // callback may have cancelled itself when it was invoked.
                if ($watcher->type === Watcher::TIMER_ONCE && isset($this->watchers[$watcher->id])) {
                    $this->clearWatcher($watcher->id);
                }
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
        return $this->watchStream($stream, $callback, Watcher::IO_READER, (bool) $enableNow);
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
        return $this->watchStream($stream, $callback, Watcher::IO_WRITER, (bool) $enableNow);
    }

    private function watchStream($stream, callable $callback, $type, $enableNow) {
        $this->enabledWatcherCount += $enableNow;
        $streamId = (int) $stream;
        $poll = isset($this->streamIdPollMap[$streamId])
            ? $this->streamIdPollMap[$streamId]
            : $this->makePollHandle($stream);

        $watcherId = $this->lastWatcherId;
        $this->watchers[$watcherId] = $watcher = new UvIoWatcher;
        $watcher->id = $watcherId = $this->lastWatcherId++;
        $watcher->type = $type;
        $watcher->poll = $poll;
        $watcher->stream = $stream;
        $watcher->callback = $callback;
        $watcher->isEnabled = $enableNow;

        if ($enableNow === false) {
            $poll->disable[$watcherId] = $watcher;
            return $watcherId;
        }

        if ($type === Watcher::IO_READER) {
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
                $this->coroutine($result)->when($this->onCallbackResolution);
            }
        } catch (\Exception $e) {
            $this->handleRunError($e);
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
        $this->enabledWatcherCount++;
        $watcher = new UvSignalWatcher;
        $watcher->id = (string) $this->lastWatcherId++;
        $watcher->type = Watcher::SIGNAL;
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
        if (isset($this->watchers[$watcherId])) {
            $this->clearWatcher($watcherId);
        }
    }

    private function clearWatcher($watcherId) {
        $watcher = $this->watchers[$watcherId];
        unset($this->watchers[$watcherId]);
        if ($watcher->isEnabled) {
            $this->enabledWatcherCount--;
            switch ($watcher->type) {
                case Watcher::IO_READER:
                    // fallthrough
                case Watcher::IO_WRITER:
                    $this->clearPollFromWatcher($watcher);
                    break;
                case Watcher::SIGNAL:
                    uv_signal_stop($watcher->uvStruct);
                    break;
                case Watcher::IMMEDIATE:
                    unset($this->immediates[$watcherId]);
                    break;
                case Watcher::TIMER_ONCE:
                    // we don't have to actually stop once timers
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

        $this->enabledWatcherCount--;
        switch ($watcher->type) {
            case Watcher::IO_READER:
                // fallthrough
            case Watcher::IO_WRITER:
                $this->disablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                uv_signal_stop($watcher->uvStruct);
                break;
            case Watcher::IMMEDIATE:
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

        $this->enabledWatcherCount++;

        switch ($watcher->type) {
            case Watcher::IO_READER:
                // fallthrough
            case Watcher::IO_WRITER:
                $this->enablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                uv_signal_start($watcher->uvStruct, $watcher->callback, $watcher->signo);
                break;
            case Watcher::IMMEDIATE:
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

        if ($watcher->type === Watcher::IO_READER) {
            $poll->flags |= \UV::READABLE;
            $poll->readers[$watcherId] = $watcher;
        } else {
            $poll->flags |= \UV::WRITABLE;
            $poll->writers[$watcherId] = $watcher;
        }

        @uv_poll_start($poll->handle, $poll->flags, $poll->callback);
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

class UvIoWatcher extends Watcher {
    // Inherited:
    // public $id;
    // public $type;
    // public $isEnabled;
    public $poll;
    public $stream;
    public $callback;
}

class UvPoll extends Struct {
    public $flags;
    public $handle;
    public $callback;
    public $readers = [];
    public $writers = [];
    public $disable = [];
}

class UvSignalWatcher extends Watcher {
    // Inherited:
    // public $id;
    // public $type;
    // public $isEnabled;
    public $signo;
    public $uvStruct;
    public $callback;
}

class UvTimerWatcher extends Watcher {
    // Inherited:
    // public $id;
    // public $type;
    // public $isEnabled;
    public $uvStruct;
    public $callback;
    public $msDelay;
    public $msInterval;
}
