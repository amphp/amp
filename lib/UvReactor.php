<?php

namespace Amp;

/**
 * @codeCoverageIgnore
 * @TODO remove code coverage ignore once we're able to install php-uv on travis
 */
class UvReactor implements Reactor {
    use Struct;

    private $loop;
    private $watchers;
    private $keepAliveCount = 0;
    private $streamIdPollMap = [];
    private $state = self::STOPPED;
    private $stopException;
    private $isWindows;
    private $immediates = [];
    private $onError;
    private $onCoroutineResolution;

    /* Pre-PHP7 closure GC hack vars */
    private $garbage;
    private $gcWatcher;
    private $gcCallback;

    public function __construct() {
        // @codeCoverageIgnoreStart
        if (!\extension_loaded("uv")) {
            throw new \RuntimeException(
                "The php-uv extension is required to use " . __CLASS__
            );
        }
        // @codeCoverageIgnoreEnd

        $this->loop = \uv_loop_new();
        $this->isWindows = (stripos(PHP_OS, 'win') === 0);

        /**
         * Prior to PHP7 we can't cancel closure watchers inside their own callbacks
         * because PHP will fatal. In legacy versions we schedule manual GC workarounds.
         *
         * @link https://bugs.php.net/bug.php?id=62452
         */
        if (PHP_MAJOR_VERSION < 7) {
            $this->garbage = [];
            $this->gcWatcher = \uv_timer_init($this->loop);
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
    }

    /**
     * {@inheritDoc}
     */
    public function run(callable $onStart = null) {
        if ($this->state !== self::STOPPED) {
            throw new \LogicException(
                "Cannot run() recursively; event reactor already active"
            );
        }

        if ($onStart) {
            $this->state = self::STARTING;
            $onStartWatcherId = $this->immediately($onStart);
            $this->tryImmediate($this->watchers[$onStartWatcherId]);
            if (empty($this->keepAliveCount) && empty($this->stopException)) {
                $this->state = self::STOPPED;
            }
        } else {
            $this->state = self::RUNNING;
        }

        while ($this->state > self::STOPPED) {
            $immediates = $this->immediates;
            foreach ($immediates as $watcher) {
                if (!$this->tryImmediate($watcher)) {
                    break;
                }
            }
            if (empty($this->keepAliveCount) || $this->state <= self::STOPPED) {
                break;
            }
            \uv_run($this->loop, $this->immediates ? \UV::RUN_NOWAIT : \UV::RUN_ONCE);
        }

        \gc_collect_cycles();

        $this->state = self::STOPPED;
        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    private function tryImmediate($watcher) {
        try {
            unset(
                $this->watchers[$watcher->id],
                $this->immediates[$watcher->id]
            );
            $this->keepAliveCount -= $watcher->keepAlive;
            $out = \call_user_func($watcher->callback, $watcher->id, $watcher->cbData);
            if ($out instanceof \Generator) {
                resolve($out)->when($this->onCoroutineResolution);
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

        return $this->state;
    }

    /**
     * {@inheritDoc}
     */
    public function tick($noWait = false) {
        if ($this->state) {
            throw new \LogicException(
                "Cannot tick() recursively; event reactor already active"
            );
        }

        $this->state = self::TICKING;

        $noWait = (bool) $noWait;
        $immediates = $this->immediates;
        foreach ($immediates as $watcher) {
            if (!$this->tryImmediate($watcher)) {
                break;
            }
        }

        // Check the conditional again because a manual stop() could've changed the state
        if ($this->state > 0) {
            \uv_run($this->loop, $noWait || $this->immediates ? \UV::RUN_NOWAIT : \UV::RUN_ONCE);
        }

        $this->state = self::STOPPED;
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
        if ($this->state !== self::STOPPED) {
            \uv_stop($this->loop);
            $this->state = self::STOPPING;
        } else {
            throw new \LogicException(
                "Cannot stop(); event reactor not currently active"
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function immediately(callable $callback, array $options = []) {
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::IMMEDIATE;
        $watcher->callback = $callback;
        $watcher->cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->keepAlive = isset($options["keep_alive"]) ? (bool) $options["keep_alive"] : true;

        $this->keepAliveCount += ($watcher->isEnabled && $watcher->keepAlive);

        if ($watcher->isEnabled) {
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
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = ($isRepeating) ? Watcher::TIMER_REPEAT : Watcher::TIMER_ONCE;
        $watcher->uvHandle = \uv_timer_init($this->loop);
        $watcher->callback = $this->wrapTimerCallback($watcher, $callback, isset($options["cb_data"]) ? $options["cb_data"] : null);
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->keepAlive = isset($options["keep_alive"]) ? (bool) $options["keep_alive"] : true;
        $this->keepAliveCount += ($watcher->isEnabled && $watcher->keepAlive);

        if (empty($watcher->keepAlive)) {
            \uv_unref($watcher->uvHandle);
        }

        $watcher->msDelay = $msDelay;
        $watcher->msInterval = $isRepeating ? $msInterval : 0;

        $this->watchers[$watcherId] = $watcher;

        if ($watcher->isEnabled) {
            \uv_timer_start($watcher->uvHandle, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
        }

        return $watcherId;
    }

    private function wrapTimerCallback($watcher, $callback, $cbData) {
        $watcherId = $watcher->id;
        $once = $watcher->type === Watcher::TIMER_ONCE;
        return function() use ($once, $watcherId, $callback, $cbData) {
            try {
                $result = \call_user_func($callback, $watcherId, $cbData);
                if ($result instanceof \Generator) {
                    resolve($result)->when($this->onCoroutineResolution);
                }
                // The isset() check is necessary because the "once" timer
                // callback may have cancelled itself when it was invoked.
                if ($once && isset($this->watchers[$watcherId])) {
                    $this->clearWatcher($watcherId);
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
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->keepAlive = isset($options["keep_alive"]) ? (bool) $options["keep_alive"] : true;

        $this->keepAliveCount += ($watcher->isEnabled && $watcher->keepAlive);

        $watcher->stream = $stream;
        $watcher->streamId = $streamId = (int) $stream;

        if (empty($this->streamIdPollMap[$streamId])) {
            $watcher->poll = $poll = $this->makePollHandle($stream);
            if (empty($watcher->keepAlive)) {
                \uv_unref($poll->handle);
            } else {
                $poll->keepAlives++;
            }
        } else {
            $watcher->poll = $poll = $this->streamIdPollMap[$streamId];
            if ($watcher->keepAlive && $poll->keepAlives++ == 0) {
                \uv_ref($poll->handle);
            }
        }

        $this->watchers[$watcherId] = $watcher;

        if (!$watcher->isEnabled) {
            $poll->disable[$watcherId] = $watcher;
            // If the poll is disabled we don't need to do anything else
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
            \uv_poll_start($poll->handle, $newFlags, $poll->callback);
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
        $readers = $writers = [];
        $poll->readers = &$readers;
        $poll->writers = &$writers;
        $poll->disable = [];
        $poll->flags = 0;
        $poll->keepAlives = 0;
        $poll->handle = \call_user_func($pollInitFunc, $this->loop, $stream);
        $poll->callback = function($uvHandle, $stat, $events) use (&$readers, &$writers) {
            if ($events & \UV::READABLE) {
                foreach ($readers as $watcher) {
                    $this->invokePollWatcher($watcher);
                }
            }
            if ($events & \UV::WRITABLE) {
                foreach ($writers as $watcher) {
                    $this->invokePollWatcher($watcher);
                }
            }
        };

        return $this->streamIdPollMap[$streamId] = $poll;
    }

    private function chooseWindowsPollingFunction($stream) {
        $streamType = stream_get_meta_data($stream)['stream_type'];

        return ($streamType === 'tcp_socket/ssl' || $streamType === 'tcp_socket')
            ? '\uv_poll_init_socket'
            : '\uv_poll_init';
    }

    private function invokePollWatcher($watcher) {
        try {
            $result = \call_user_func($watcher->callback, $watcher->id, $watcher->stream, $watcher->cbData);
            if ($result instanceof \Generator) {
                resolve($result)->when($this->onCoroutineResolution);
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
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $func, array $options = []) {
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::SIGNAL;
        $watcher->signo = $signo;
        $watcher->callback = $this->wrapSignalCallback($watcher, $func, isset($options["cb_data"]) ? $options["cb_data"] : null);
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->keepAlive = isset($options["keep_alive"]) ? (bool) $options["keep_alive"] : true;
        $this->keepAliveCount += ($watcher->isEnabled && $watcher->keepAlive);
        $watcher->uvHandle = \uv_signal_init($this->loop);
        if (empty($watcher->keepAlive)) {
            \uv_unref($watcher->uvHandle);
        }
        if ($watcher->isEnabled) {
            \uv_signal_start($watcher->uvHandle, $watcher->callback, $watcher->signo);
        }
        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    private function wrapSignalCallback($watcher, $callback, $cbData) {
        $watcherId = $watcher->id;
        $signo = $watcher->signo;
        return function() use ($watcherId, $signo, $callback, $cbData) {
            try {
                $result = \call_user_func($callback, $watcherId, $signo, $cbData);
                if ($result instanceof \Generator) {
                    resolve($result)->when($this->onCoroutineResolution);
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
            $this->keepAliveCount -= $watcher->keepAlive;
            switch ($watcher->type) {
                case Watcher::IO_READER:
                    // fallthrough
                case Watcher::IO_WRITER:
                    $this->clearPollFromWatcher($watcher, $unref = $watcher->keepAlive);
                    break;
                case Watcher::SIGNAL:
                    \uv_signal_stop($watcher->uvHandle);
                    break;
                case Watcher::IMMEDIATE:
                    unset($this->immediates[$watcherId]);
                    break;
                case Watcher::TIMER_ONCE:
                case Watcher::TIMER_REPEAT:
                    @\uv_timer_stop($watcher->uvHandle);
                    break;
            }
        } elseif ($watcher->type == Watcher::IO_READER || $watcher->type == Watcher::IO_WRITER) {
            $this->clearPollFromWatcher($watcher, $unref = false);
        }

        if (PHP_MAJOR_VERSION < 7) {
            $this->garbage[] = $watcher;
            if (!$this->isGcScheduled) {
                \uv_timer_start($this->gcWatcher, 250, 0, $this->gcCallback);
                $this->isGcScheduled = true;
            }
        }
    }

    private function clearPollFromWatcher($watcher, $unref) {
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

        if ($unref && --$poll->keepAlives == 0) {
            \uv_unref($poll->handle);
        }

        // Always stop polling if no enabled watchers remain
        \uv_poll_stop($poll->handle);

        // If all watchers are disabled we can pull out here
        if ($poll->disable) {
            return;
        }

        // Otherwise there are no watchers left for this poll and we should clear it
        $streamId = (int) $watcher->stream;
        unset($this->streamIdPollMap[$streamId]);

        // Force explicit handle close as libuv does not like two handles simultaneously existing for a same file descriptor (it is referenced until handle close callback end)
        \uv_close($poll->handle);
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

        $watcher->isEnabled = false;
        $this->keepAliveCount -= $watcher->keepAlive;

        switch ($watcher->type) {
            case Watcher::IO_READER:
                // fallthrough
            case Watcher::IO_WRITER:
                $this->disablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                \uv_signal_stop($watcher->uvHandle);
                break;
            case Watcher::IMMEDIATE:
                unset($this->immediates[$watcherId]);
                break;
            case Watcher::TIMER_ONCE:
                // fallthrough
            case Watcher::TIMER_REPEAT:
                \uv_timer_stop($watcher->uvHandle);
                break;
            default:
                throw new \RuntimeException("Unexpected Watcher type encountered");
        }
    }

    private function disablePollFromWatcher($watcher) {
        $poll = $watcher->poll;
        $watcherId = $watcher->id;

        unset(
            $poll->readers[$watcherId],
            $poll->writers[$watcherId]
        );

        $poll->disable[$watcherId] = $watcher;

        if ($watcher->keepAlive && --$poll->keepAlives == 0) {
            \uv_unref($poll->handle);
        }

        if (!($poll->readers || $poll->writers)) {
            \uv_poll_stop($poll->handle);
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
            \uv_poll_start($poll->handle, $newFlags, $poll->callback);
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

        $watcher->isEnabled = true;
        $this->keepAliveCount += $watcher->keepAlive;

        switch ($watcher->type) {
            case Watcher::TIMER_ONCE: // fallthrough
            case Watcher::TIMER_REPEAT:
                \uv_timer_start($watcher->uvHandle, $watcher->msDelay, $watcher->msInterval, $watcher->callback);
                break;
            case Watcher::IO_READER: // fallthrough
            case Watcher::IO_WRITER:
                $this->enablePollFromWatcher($watcher);
                break;
            case Watcher::SIGNAL:
                \uv_signal_start($watcher->uvHandle, $watcher->callback, $watcher->signo);
                break;
            case Watcher::IMMEDIATE:
                $this->immediates[$watcherId] = $watcher;
                break;
            default:
                throw new \RuntimeException("Unexpected Watcher type encountered");
        }
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

        if ($watcher->keepAlive && $poll->keepAlives++ == 0) {
            \uv_ref($poll->handle);
        }

        @\uv_poll_start($poll->handle, $poll->flags, $poll->callback);
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
        $this->keepAliveCount++;
    }

    /**
     * Manually decrement the watcher refcount
     *
     * @return void
     */
    public function delRef() {
        $this->keepAliveCount--;
    }

    /**
     * {@inheritDoc}
     */
    public function onError(callable $callback) {
        $this->onError = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function info() {
        $once = $repeat = $immediately = $onReadable = $onWritable = $onSignal = [
            "enabled" => 0,
            "disabled" => 0,
        ];
        foreach ($this->watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::IMMEDIATE:    $arr =& $immediately;   break;
                case Watcher::TIMER_ONCE:   $arr =& $once;          break;
                case Watcher::TIMER_REPEAT: $arr =& $repeat;        break;
                case Watcher::IO_READER:    $arr =& $onReadable;    break;
                case Watcher::IO_WRITER:    $arr =& $onWritable;    break;
                case Watcher::SIGNAL:       $arr =& $onSignal;      break;
            }
            if ($watcher->isEnabled) {
                $arr["enabled"] += 1;
            } else {
                $arr["disabled"] += 1;
            }
        }

        return [
            "immediately"       => $immediately,
            "once"              => $once,
            "repeat"            => $repeat,
            "on_readable"       => $onReadable,
            "on_writable"       => $onWritable,
            "on_signal"         => $onSignal,
            "keep_alive"        => $this->keepAliveCount,
            "state"             => $this->state,
        ];
    }

    /**
     * Access the underlying php-uv extension loop resource
     *
     * This method provides access to the underlying php-uv event loop resource for
     * code that wishes to interact with lower-level php-uv extension functionality.
     *
     * @return resource
     */
    public function getLoop() {
        return $this->loop;
    }

    public function __debugInfo() {
        return $this->info();
    }
}
