<?php

namespace Amp;

/**
 * @codeCoverageIgnore
 * @TODO remove code coverage ignore once we're able to install php-uv on travis
 */
class UvReactor implements ExtensionReactor {
    private $loop;
    private $lastWatcherId = "a";
    private $watchers;
    private $enabledWatcherCount = 0;
    private $streamIdPollMap = [];
    private $isRunning = false;
    private $stopException;
    private $resolution = 1000;
    private $isWindows;
    private $immediates = [];
    private $onError;
    private $onCoroutineResolution;

    /* Pre-PHP7 closure GC hack vars */
    private $garbage;
    private $gcWatcher;
    private $gcCallback;

    private static $instanceCount = 0;

    public function __construct() {
        if (!extension_loaded("uv")) {
            throw new \RuntimeException(
                "The php-uv extension is required to use the UvReactor."
            );
        }

        $this->loop = uv_loop_new();
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);

        /**
         * Prior to PHP7 we can't cancel closure watchers inside their own callbacks
         * because PHP will fatal. In legacy versions we schedule manual GC workarounds.
         *
         * @link https://bugs.php.net/bug.php?id=62452
         */
        if (PHP_MAJOR_VERSION < 7) {
            $this->garbage = [];
            $this->gcWatcher = uv_timer_init($this->loop);
            $this->gcCallback = function() {
                $this->garbage = [];
                $this->isGcScheduled = false;
            };
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
        foreach ($immediates as $watcherId => $watcher) {
            try {
                $this->enabledWatcherCount--;
                unset(
                    $this->immediates[$watcherId],
                    $this->watchers[$watcherId]
                );
                $result = call_user_func($watcher->callback, $this, $watcherId, $watcher->callbackData);
                if ($result instanceof \Generator) {
                    Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
                }
            } catch (\Exception $e) {
                $this->onCallbackError($e);
            }

            if (!$this->isRunning) {
                // If a watcher stops the reactor break out of the loop
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

        $noWait = (bool) $noWait;
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
     * {@inheritDoc}
     */
    public function stop() {
        uv_stop($this->loop);
        $this->isRunning = false;
    }

    /**
     * {@inheritDoc}
     */
    public function immediately(callable $callback, array $options = []) {
        $watcher = new \StdClass;
        $watcher->id = $watcherId = $this->lastWatcherId++;
        $watcher->type = Watcher::IMMEDIATE;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            $this->immediates[$watcherId] = $watcher;
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        assert(($msDelay >= 0), "\$msDelay at Argument 2 expects integer >= 0");
        return $this->registerTimer($callback, $msDelay, $msInterval = -1, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function repeat(callable $callback, $msInterval, array $options = []) {
        assert(($msInterval >= 0), "\$msInterval at Argument 2 expects integer >= 0");
        $msDelay = isset($options["ms_delay"]) ? $options["ms_delay"] : $msInterval;
        assert(($msDelay >= 0), "ms_delay option expects integer >= 0");

        // libuv interprets a zero interval as "non-repeating." Because we support
        // zero-time repeat intervals in our other event reactors we hack in support
        // for this by assigning a 1ms interval when zero is passed by the user.
        if ($msInterval === 0) {
            $msInterval = 1;
        }

        return $this->registerTimer($callback, $msDelay, $msInterval, $options);
    }

    private function registerTimer(callable $callback, $msDelay, $msInterval, array $options) {
        $isRepeating = ($msInterval !== -1);

        $watcher = new \StdClass;
        $watcher->id = $watcherId = $this->lastWatcherId++;
        $watcher->type = ($isRepeating) ? Watcher::TIMER_REPEAT : Watcher::TIMER_ONCE;
        $watcher->uvHandle = uv_timer_init($this->loop);
        $watcher->callback = $this->wrapTimerCallback($watcher, $callback);
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->msDelay = $msDelay;
        $watcher->msInterval = $isRepeating ? $msInterval : 0;

        $this->watchers[$watcherId] = $watcher;

        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            uv_timer_start($watcher->uvHandle, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
        }

        return $watcherId;
    }

    private function wrapTimerCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $watcherId = $watcher->id;
                $result = \call_user_func($callback, $this, $watcherId, $watcher->callbackData);
                if ($result instanceof \Generator) {
                    Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
                }
                // The isset() check is necessary because the "once" timer
                // callback may have cancelled itself when it was invoked.
                if ($watcher->type === Watcher::TIMER_ONCE && isset($this->watchers[$watcherId])) {
                    $this->clearWatcher($watcherId);
                }
            } catch (\Exception $e) {
                $this->onCallbackError($e);
            }
        };
    }

    private function onCallbackError(\Exception $e) {
        if (empty($this->onError)) {
            $this->stopException = $e;
            $this->stop();
        } else {
            $this->tryUserErrorCallback($e);
        }
    }

    private function tryUserErrorCallback(\Exception $e) {
        try {
            call_user_func($this->onError, $e);
        } catch (\Exception $e) {
            $this->stopException = $e;
            $this->stop();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        return $this->watchStream($stream, $callback, Watcher::IO_READER, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        return $this->watchStream($stream, $callback, Watcher::IO_WRITER, $options);
    }

    private function watchStream($stream, callable $callback, $type, array $options) {
        $watcherId = $this->lastWatcherId++;
        $watcher = new \StdClass;
        $watcher->id = $watcherId;
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->stream = $stream;
        $watcher->streamId = $streamId = (int) $stream;
        $watcher->poll = $poll = isset($this->streamIdPollMap[$streamId])
            ? $this->streamIdPollMap[$streamId]
            : $this->makePollHandle($stream);

        $this->watchers[$watcherId] = $watcher;

        if (!$watcher->isEnabled) {
            $poll->disable[$watcherId] = $watcher;
            // If the poll is disabled we don't need to do anything else
            return $watcherId;
        }

        $this->enabledWatcherCount++;

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

        $poll = new \StdClass;
        $poll->readers = [];
        $poll->writers = [];
        $poll->disable = [];
        $poll->flags = 0;
        $poll->handle = \call_user_func($pollInitFunc, $this->loop, $stream);
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

        return $this->streamIdPollMap[$streamId] = $poll;
    }

    private function chooseWindowsPollingFunction($stream) {
        $streamType = stream_get_meta_data($stream)['stream_type'];

        return ($streamType === 'tcp_socket/ssl' || $streamType === 'tcp_socket')
            ? 'uv_poll_init_socket'
            : 'uv_poll_init';
    }

    private function invokePollWatcher($watcher) {
        try {
            $result = call_user_func($watcher->callback, $this, $watcher->id, $watcher->stream, $watcher->callbackData);
            if ($result instanceof \Generator) {
                Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
            }
        } catch (\Exception $e) {
            $this->onCallbackError($e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $func, array $options = []) {
        $watcher = new \StdClass;
        $watcher->id = $watcherId = $this->lastWatcherId++;
        $watcher->type = Watcher::SIGNAL;
        $watcher->callback = $this->wrapSignalCallback($watcher, $func);
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->signo = $signo;
        $watcher->uvHandle = uv_signal_init($this->loop);

        if ($watcher->isEnabled) {
            $this->enabledWatcherCount++;
            uv_signal_start($watcher->uvHandle, $watcher->callback, $watcher->signo);
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    private function wrapSignalCallback($watcher, $callback) {
        return function() use ($watcher, $callback) {
            try {
                $result = call_user_func($callback, $this, $watcher->id, $watcher->signo, $watcher->callbackData);
                if ($result instanceof \Generator) {
                    Coroutine::resolve($result, $this)->when($this->onCoroutineResolution);
                }
            } catch (\Exception $e) {
                $this->onCallbackError($e);
            }
        };
    }

    /**
     * {@inheritDoc}
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
                    uv_signal_stop($watcher->uvHandle);
                    break;
                case Watcher::IMMEDIATE:
                    unset($this->immediates[$watcherId]);
                    break;
                case Watcher::TIMER_ONCE:
                case Watcher::TIMER_REPEAT:
                    @uv_timer_stop($watcher->uvHandle);
                    break;
            }
        } elseif ($watcher->type == Watcher::IO_READER || $watcher->type == Watcher::IO_WRITER) {
            $this->clearPollFromWatcher($watcher);
        }

        if (PHP_MAJOR_VERSION < 7) {
            $this->garbage[] = $watcher;
            if (!$this->isGcScheduled) {
                uv_timer_start($this->gcWatcher, 250, 0, $this->gcCallback);
                $this->isGcScheduled = true;
            }
        }
    }

    private function clearPollFromWatcher($watcher) {
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
     * {@inheritDoc}
     */
    public function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if (!$watcher->isEnabled) {
            return;
        }

        switch ($watcher->type) {
            case Watcher::IO_READER:
                // fallthrough
            case Watcher::IO_WRITER:
                $this->disablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                uv_signal_stop($watcher->uvHandle);
                break;
            case Watcher::IMMEDIATE:
                unset($this->immediates[$watcherId]);
                break;
            case Watcher::TIMER_ONCE:
                // fallthrough
            case Watcher::TIMER_REPEAT:
                uv_timer_stop($watcher->uvHandle);
                break;
            default:
                throw new \RuntimeException("Unexpected Watcher type encountered");
        }

        $watcher->isEnabled = false;
        $this->enabledWatcherCount--;
    }

    private function disablePollFromWatcher($watcher) {
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
     * {@inheritDoc}
     */
    public function enable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            return;
        }

        switch ($watcher->type) {
            case Watcher::TIMER_ONCE: // fallthrough
            case Watcher::TIMER_REPEAT:
                uv_timer_start($watcher->uvHandle, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
                break;
            case Watcher::IO_READER: // fallthrough
            case Watcher::IO_WRITER:
                $this->enablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                uv_signal_start($watcher->uvHandle, $watcher->callback, $watcher->signo);
                break;
            case Watcher::IMMEDIATE:
                $this->immediates[$watcherId] = $watcher;
                break;
            default:
                throw new \RuntimeException("Unexpected Watcher type encountered");
        }

        $watcher->isEnabled = true;
        $this->enabledWatcherCount++;
    }

    private function enablePollFromWatcher($watcher) {
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
     * Manually increment the watcher refcount
     *
     * This method, like it's delRef() counterpart, is *only* necessary when manually
     * operating directly on the underlying uv loop to avoid the reactor's run loop
     * exiting because no enabled watchers exist when waiting on manually registered
     * uv_*() callbacks.
     *
     * @return void
     */
    public function addRef() {
        $this->enabledWatcherCount++;
    }

    /**
     * Manually decrement the watcher refcount
     *
     * @return void
     */
    public function delRef() {
        $this->enabledWatcherCount--;
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
