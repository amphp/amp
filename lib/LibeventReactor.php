<?php

namespace Amp;

class LibeventReactor implements Reactor {
    use Struct;

    private $base;
    private $keepAliveBase;
    private $watchers = [];
    private $immediates = [];
    private $keepAliveCount = 0;
    private $state = self::STOPPED;
    private $stopException;
    private $onError;
    private $onCoroutineResolution;

    /* Pre-PHP7 closure GC hack vars */
    private $gcEvent;
    private $garbage = [];
    private $isGcScheduled = false;

    public function __construct() {
        // @codeCoverageIgnoreStart
        if (!\extension_loaded("libevent")) {
            throw new \RuntimeException(
                "The pecl libevent extension is required to use " . __CLASS__
            );
        }
        // @codeCoverageIgnoreEnd

        $this->base = \event_base_new();
        $this->keepAliveBase = \event_base_new();

        /**
         * Prior to PHP7 we can't cancel closure watchers inside their own callbacks
         * because PHP will fatal. In legacy versions we schedule manual GC workarounds.
         *
         * @link https://bugs.php.net/bug.php?id=62452
         */
        if (PHP_MAJOR_VERSION < 7) {
            $this->gcEvent = event_new();
            \event_timer_set($this->gcEvent, function() {
                $this->garbage = [];
                $this->isGcScheduled = false;
                \event_del($this->gcEvent);
            });
            \event_base_set($this->gcEvent, $this->keepAliveBase);
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
            $flags = \EVLOOP_ONCE | \EVLOOP_NONBLOCK;
            \event_base_loop($this->base, $flags);
            $flags = empty($this->immediates) ? \EVLOOP_ONCE : (\EVLOOP_ONCE | \EVLOOP_NONBLOCK);
            \event_base_loop($this->keepAliveBase, $flags);
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
            $this->keepAliveCount -= $watcher->keepAlive;
            unset(
                $this->watchers[$watcher->id],
                $this->immediates[$watcher->id]
            );
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
        $immediates = $this->immediates;
        foreach ($immediates as $watcher) {
            if (!$this->tryImmediate($watcher)) {
                break;
            }
        }

        // Check the conditional again because a manual stop() could've changed the state
        if ($this->state) {
            $flags = \EVLOOP_ONCE | \EVLOOP_NONBLOCK;
            \event_base_loop($this->base, \EVLOOP_ONCE | \EVLOOP_NONBLOCK);
            $flags = $noWait || !empty($this->immediates) ? (EVLOOP_ONCE | EVLOOP_NONBLOCK) : EVLOOP_ONCE;
            \event_base_loop($this->keepAliveBase, $flags);
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
            \event_base_loopexit($this->base);
            \event_base_loopexit($this->keepAliveBase);
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
        $watcher = $this->initWatcher(Watcher::IMMEDIATE, $callback, $options);
        if ($watcher->isEnabled) {
            $this->immediates[$watcher->id] = $watcher;
        }

        return $watcher->id;
    }

    private function initWatcher($type, $callback, $options) {
        $watcher = new \StdClass;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->keepAlive = isset($options["keep_alive"]) ? (bool) $options["keep_alive"] : true;
        $this->keepAliveCount += ($watcher->isEnabled && $watcher->keepAlive);

        if ($type !== Watcher::IMMEDIATE) {
            $watcher->eventResource = \event_new();
            $watcher->eventBase = $watcher->keepAlive ? $this->keepAliveBase : $this->base;
        }

        $this->watchers[$watcher->id] = $watcher;

        return $watcher;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        assert(($msDelay >= 0), "\$msDelay at Argument 2 expects integer >= 0");
        $watcher = $this->initWatcher(Watcher::TIMER_ONCE, $callback, $options);
        $watcher->msDelay = ($msDelay * 1000);
        \event_timer_set($watcher->eventResource, $this->wrapCallback($watcher));
        \event_base_set($watcher->eventResource, $watcher->eventBase);
        if ($watcher->isEnabled) {
            \event_add($watcher->eventResource, $watcher->msDelay);
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
                        $result = \call_user_func($watcher->callback, $watcher->id, $watcher->stream, $watcher->cbData);
                        break;
                    case Watcher::TIMER_ONCE:
                        $result = \call_user_func($watcher->callback, $watcher->id, $watcher->cbData);
                        $this->cancel($watcher->id);
                        break;
                    case Watcher::TIMER_REPEAT:
                        $result = \call_user_func($watcher->callback, $watcher->id, $watcher->cbData);
                        // If the watcher cancelled itself this will no longer exist
                        if (isset($this->watchers[$watcher->id])) {
                            event_add($watcher->eventResource, $watcher->msInterval);
                        }
                        break;
                    case Watcher::SIGNAL:
                        $result = \call_user_func($watcher->callback, $watcher->id, $watcher->signo, $watcher->cbData);
                        break;
                }
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
        $msInterval *= 1000;
        if (isset($options["ms_delay"])) {
            $msDelay = (int) $options["ms_delay"];
            assert(($msDelay >= 0), "ms_delay option expects integer >= 0");
            $msDelay *= 1000;
        } else {
            $msDelay = $msInterval;
        }

        $watcher = $this->initWatcher(Watcher::TIMER_REPEAT, $callback, $options);
        $watcher->msInterval = (int) $msInterval;
        \event_timer_set($watcher->eventResource, $this->wrapCallback($watcher));
        \event_base_set($watcher->eventResource, $watcher->eventBase);
        if ($watcher->isEnabled) {
            \event_add($watcher->eventResource, $msDelay);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::IO_READER, $callback, $options);
        $watcher->stream = $stream;
        $evFlags = \EV_PERSIST | \EV_READ;
        \event_set($watcher->eventResource, $stream, $evFlags, $this->wrapCallback($watcher));
        \event_base_set($watcher->eventResource, $watcher->eventBase);
        if ($watcher->isEnabled) {
            \event_add($watcher->eventResource);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::IO_WRITER, $callback, $options);
        $watcher->stream = $stream;
        $evFlags = \EV_PERSIST | \EV_WRITE;
        \event_set($watcher->eventResource, $stream, $evFlags, $this->wrapCallback($watcher));
        \event_base_set($watcher->eventResource, $watcher->eventBase);
        if ($watcher->isEnabled) {
            \event_add($watcher->eventResource);
        }

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::SIGNAL, $callback, $options);
        $watcher->signo = $signo = (int) $signo;
        $evFlags = \EV_SIGNAL | \EV_PERSIST;
        \event_set($watcher->eventResource, $signo, $evFlags, $this->wrapCallback($watcher));
        \event_base_set($watcher->eventResource, $watcher->eventBase);
        if ($watcher->isEnabled) {
            \event_add($watcher->eventResource);
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
            $this->keepAliveCount -= $watcher->keepAlive;
        }

        if ($watcher->type === Watcher::IMMEDIATE) {
            unset(
                $this->immediates[$watcherId],
                $this->watchers[$watcherId]
            );
        } else {
            \event_del($watcher->eventResource);
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

        $watcher->isEnabled = false;
        $this->keepAliveCount -= $watcher->keepAlive;

        switch ($watcher->type) {
            case Watcher::IMMEDIATE:
                unset($this->immediates[$watcherId]);
                break;
            case Watcher::IO_READER:    // fallthrough
            case Watcher::IO_WRITER:    // fallthrough
            case Watcher::SIGNAL:       // fallthrough
            case Watcher::TIMER_ONCE:   // fallthrough
            case Watcher::TIMER_REPEAT:
                \event_del($watcher->eventResource);
                break;
        }
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

        $watcher->isEnabled = true;
        $this->keepAliveCount += $watcher->keepAlive;

        switch ($watcher->type) {
            case Watcher::IMMEDIATE:
                $this->immediates[$watcherId] = $watcher;
                break;
            case Watcher::TIMER_ONCE:
                \event_add($watcher->eventResource, $watcher->msDelay);
                break;
            case Watcher::TIMER_REPEAT:
                \event_add($watcher->eventResource, $watcher->msInterval);
                break;
            case Watcher::IO_READER: // fallthrough
            case Watcher::IO_WRITER: // fallthrough
            case Watcher::SIGNAL:
                \event_add($watcher->eventResource);
                break;
        }
    }

    private function scheduleGarbageCollection() {
        if (!$this->isGcScheduled) {
            \event_add($this->gcEvent, 0);
            $this->isGcScheduled = true;
        }
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
     * Access the underlying libevent extension event base
     *
     * This method provides access to the underlying libevent event loop resource for
     * code that wishes to interact with lower-level libevent extension functionality.
     *
     * @return resource
     */
    public function getLoop() {
        return $this->keepAliveBase;
    }

    public function __debugInfo() {
        return $this->info();
    }
}
