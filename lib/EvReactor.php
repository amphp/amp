<?php

namespace Amp;

use Ev;
use EvIo;
use EvLoop;
use EvTimer;
use EvSignal;

class EvReactor implements Reactor {
    private $loop;
    private $lastWatcherId = "a";
    private $enabledWatchers = [];
    private $disabledWatchers = [];
    private $enabledImmediates = [];
    private $disabledImmediates = [];
    private $isRunning = false;
    private $stopException;
    private $onError;
    private $onCoroutineResolution;

    public function __construct($flags = null) {
        // @codeCoverageIgnoreStart
        if (!extension_loaded("ev")) {
            throw new \RuntimeException(
                "The pecl ev extension is required to use " . __CLASS__
            );
        }
        // @codeCoverageIgnoreEnd

        $flags = $flags ?: Ev::FLAG_AUTO;
        $this->loop = new EvLoop($flags);
        $this->onCoroutineResolution = function ($e = null, $r = null) {
            if ($e) {
                $this->onCallbackError($e);
            }
        };
    }

    public function __destruct() {
        foreach (array_keys($this->enabledWatchers) as $watcherId) {
            $this->cancel($watcherId);
        }
        foreach (array_keys($this->disabledWatchers) as $watcherId) {
            $this->cancel($watcherId);
        }
    }

    /**
     * {@inheritdoc}
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
            $immediates = $this->enabledImmediates;
            foreach ($immediates as $watcherId => $immediateArr) {
                list($callback, $cbData) = $immediateArr;
                if (!$this->tryImmediate($watcherId, $callback, $cbData)) {
                    break;
                }
            }
            if (!$this->isRunning || !($this->enabledWatchers || $this->enabledImmediates)) {
                break;
            }
            $flags = $this->enabledImmediates ? (Ev::RUN_ONCE | Ev::RUN_NOWAIT) : Ev::RUN_ONCE;
            $this->loop->run($flags);
        }

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    private function tryImmediate($watcherId, $callback, $cbData) {
        try {
            unset($this->enabledImmediates[$watcherId]);
            $out = \call_user_func($callback, $watcherId, $cbData);
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

        return $this->isRunning;
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
     * {@inheritdoc}
     */
    public function tick($noWait = false) {
        $noWait = (bool) $noWait;
        $this->isRunning = true;

        $immediates = $this->enabledImmediates;
        foreach ($immediates as $watcherId => $immediateArr) {
            list($callback, $cbData) = $immediateArr;
            if (!$this->tryImmediate($watcherId, $callback, $cbData)) {
                break;
            }
        }

        if ($this->isRunning) {
            $flags = $noWait || $this->enabledImmediates ? Ev::RUN_NOWAIT | Ev::RUN_ONCE : Ev::RUN_ONCE;
            $this->loop->run($flags);
            $this->isRunning = false;
        }

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
        $this->loop->stop();
        $this->isRunning = false;
    }

    /**
     * {@inheritdoc}
     */
    public function immediately(callable $callback, array $options = []) {
        $watcherId = $this->lastWatcherId++;
        $cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        if ($isEnabled) {
            $this->enabledImmediates[$watcherId] = [$callback, $cbData];
        } else {
            $this->disabledImmediates[$watcherId] = [$callback, $cbData];
        }

        return $watcherId;
    }

    /**
     * {@inheritdoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        $type = Watcher::TIMER_ONCE;
        $msInterval = 0.0;
        // @TODO assert($msDelay > 0)

        return $this->registerTimer($type, $callback, $msDelay, $msInterval, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function repeat(callable $callback, $msInterval, array $options = []) {
        $type = Watcher::TIMER_REPEAT;
        $msDelay = isset($options["ms_delay"]) ? $options["ms_delay"] : $msInterval;
        if ($msInterval == 0) {
            $msInterval = 1;
        }

        return $this->registerTimer($type, $callback, $msDelay, $msInterval, $options);
    }

    private function registerTimer($type, $callback, $msDelay, $msInterval, $options = []) {
        // ev uses one second resolution
        $msDelay = $msDelay/1000;
        $msInterval = $msInterval/1000;
        $watcherId = $this->lastWatcherId++;
        $wrappedCallback = $this->wrapWatcherCallback($type, $watcherId, $callback);
        $cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher = $this->loop->timer($msDelay, $msInterval, $wrappedCallback, $cbData);
        if ($isEnabled) {
            $this->enabledWatchers[$watcherId] = $watcher;
        } else {
            $watcher->stop();
            $this->disabledWatchers[$watcherId] = $watcher;
        }

        return $watcherId;
    }

    private function wrapWatcherCallback($type, $watcherId, $callback, $stream = null) {
        return function($evHandle) use ($type, $watcherId, $callback, $stream) {
            try {
                if ($type === Watcher::TIMER_ONCE) {
                    unset($this->enabledWatchers[$watcherId]);
                    $out = \call_user_func($callback, $watcherId, $evHandle->data);
                } elseif ($type & Watcher::IO) {
                    $out = \call_user_func($callback, $watcherId, $stream, $evHandle->data);
                } else {
                    $out = \call_user_func($callback, $watcherId, $evHandle->data);
                }
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
        };
    }

    /**
     * {@inheritdoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        return $this->registerIo($events = Ev::READ, $stream, $callback, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        return $this->registerIo($events = Ev::WRITE, $stream, $callback, $options);
    }

    private function registerIo($events, $stream, $callback, $options) {
        $watcherId = $this->lastWatcherId++;
        $type = Watcher::IO;
        $wrappedCallback = $this->wrapWatcherCallback($type, $watcherId, $callback, $stream);
        $cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        $watcher = $this->loop->io($stream, $events, $wrappedCallback, $cbData);
        if ($isEnabled) {
            $this->enabledWatchers[$watcherId] = $watcher;
        } else {
            $watcher->stop();
            $this->disabledWatchers[$watcherId] = $watcher;
        }

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $callback, array $options = []) {
        $watcherId = $this->lastWatcherId++;
        $type = Watcher::SIGNAL;
        $wrappedCallback = $this->wrapWatcherCallback($type, $watcherId, $callback);
        $cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        $watcher = $this->loop->signal($signo, $wrappedCallback, $cbData);
        if ($isEnabled) {
            $this->enabledWatchers[$watcherId] = $watcher;
        } else {
            $watcher->stop();
            $this->disabledWatchers[$watcherId] = $watcher;
        }

        return $watcherId;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherId) {
        if (isset($this->enabledWatchers[$watcherId])) {
            $watcher = $this->enabledWatchers[$watcherId];
            $watcher->clear();
            $watcher->stop();
            unset($this->enabledWatchers[$watcherId]);
        } elseif (isset($this->disabledWatchers[$watcherId])) {
            $watcher = $this->disabledWatchers[$watcherId];
            $watcher->clear();
            unset($this->disabledWatchers[$watcherId]);
        } elseif (isset($this->enabledImmediates[$watcherId])) {
            $this->enabledImmediates[$watcherId];
            unset($this->enabledImmediates[$watcherId]);
        } elseif (isset($this->disabledImmediates[$watcherId])) {
            $this->disabledImmediates[$watcherId];
            unset($this->disabledImmediates[$watcherId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disable($watcherId) {
        if (isset($this->enabledWatchers[$watcherId])) {
            $evHandle = $this->enabledWatchers[$watcherId];
            unset($this->enabledWatchers[$watcherId]);
            $this->disabledWatchers[$watcherId] = $evHandle;
            $evHandle->stop();
        } elseif (isset($this->enabledImmediates[$watcherId])) {
            $immediateArr = $this->enabledImmediates[$watcherId];
            $this->disabledImmediates[$watcherId] = $immediateArr;
            unset($this->enabledImmediates[$watcherId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function enable($watcherId) {
        if (isset($this->disabledWatchers[$watcherId])) {
            $evHandle = $this->disabledWatchers[$watcherId];
            unset($this->disabledWatchers[$watcherId]);
            $this->enabledWatchers[$watcherId] = $evHandle;
            $evHandle->start();
        } elseif (isset($this->disabledImmediates[$watcherId])) {
            $immediateArr = $this->disabledImmediates[$watcherId];
            $this->enabledImmediates[$watcherId] = $immediateArr;
            unset($this->disabledImmediates[$watcherId]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onError(callable $callback) {
        $this->onError = $callback;
    }

    /**
     * {@inheritDoc}
     */
    public function info() {
        $once = $repeat = $onReadable = $onWritable = $onSignal = [
            "enabled"  => 0,
            "disabled" => 0,
        ];
        $immediately = [
            "enabled"  => count($this->enabledImmediates),
            "disabled" => count($this->disabledImmediates),
        ];

        foreach (["enabled", "disabled"] as $key) {
            foreach ($this->{"{$key}Watchers"} as $evHandle) {
                switch (get_class($evHandle)) {
                    case "EvTimer":
                        if ($evHandle->repeat === 0.0) {
                            $once[$key] += 1;
                        } else {
                            $repeat[$key] += 1;
                        }
                        break;
                    case "EvIo":
                        if ($evHandle->events === Ev::READ) {
                            $onReadable[$key] += 1;
                        } else {
                            $onWritable[$key] += 1;
                        }
                        break;
                    case "EvSignal":
                        $onSignal[$key] += 1;
                        break;
                }
            }
        }

        return [
            "immediately"       => $immediately,
            "once"              => $once,
            "repeat"            => $repeat,
            "on_readable"       => $onReadable,
            "on_writable"       => $onWritable,
            "on_signal"         => $onSignal,
            "last_watcher_id"   => $this->lastWatcherId,
        ];
    }

    /**
     * Access the underlying ev extension loop instance
     *
     * This method provides access to the underlying ev event loop object for
     * code that wishes to interact with lower-level ev extension functionality.
     *
     * @return \EvLoop
     */
    public function getLoop() {
        return $this->loop;
    }

    public function __debugInfo() {
        return $this->info();
    }
}
