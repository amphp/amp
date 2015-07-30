<?php

namespace Amp;

use Ev;
use EvIo;
use EvLoop;
use EvTimer;
use EvSignal;

class EvReactor implements Reactor {
    use Struct;

    private $loop;
    private $watchers = [];
    private $immediates = [];
    private $watcherCallback;
    private $enabledNonImmediates = 0;
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
        $this->watcherCallback = function($evHandle, $revents) {
            try {
                $w = $evHandle->data;
                switch ($w->type) {
                    case Watcher::IO_READER:
                        // fallthrough
                    case Watcher::IO_WRITER:
                        $out = \call_user_func($w->callback, $w->id, $w->stream, $w->cbData);
                        break;
                    case Watcher::TIMER_ONCE:
                        $this->enabledNonImmediates--;
                        unset($this->watchers[$w->id]);
                        $out = \call_user_func($w->callback, $w->id, $w->cbData);
                        break;
                    case Watcher::TIMER_REPEAT:
                        $out = \call_user_func($w->callback, $w->id, $w->cbData);
                        break;
                    case Watcher::SIGNAL:
                        $out = \call_user_func($w->callback, $w->id, $w->signo, $w->cbData);
                        break;
                    default:
                        // this is an error
                        return;
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
    public function run(callable $onStart = null) {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        if ($onStart) {
            $this->immediately($onStart);
        }

        while ($this->isRunning) {
            $immediates = $this->immediates;
            foreach ($immediates as $watcher) {
                if (!$this->tryImmediate($watcher)) {
                    break;
                }
            }
            if (!$this->isRunning || !($this->enabledNonImmediates || $this->immediates)) {
                break;
            }
            $flags = $this->immediates ? (Ev::RUN_ONCE | Ev::RUN_NOWAIT) : Ev::RUN_ONCE;
            $this->loop->run($flags);
        }

        if ($this->stopException) {
            $e = $this->stopException;
            $this->stopException = null;
            throw $e;
        }
    }

    private function tryImmediate($watcher) {
        try {
            unset($this->immediates[$watcher->id]);
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

        $immediates = $this->immediates;
        foreach ($immediates as $watcher) {
            if (!$this->tryImmediate($watcher)) {
                break;
            }
        }

        if ($this->isRunning) {
            $flags = $noWait || $this->immediates ? Ev::RUN_NOWAIT | Ev::RUN_ONCE : Ev::RUN_ONCE;
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
        $watcher = $this->initWatcher(Watcher::IMMEDIATE, $callback, $options);
        if ($watcher->isEnabled) {
            $this->immediates[$watcher->id] = $watcher;
        }
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    private function initWatcher($type, $callback, $options) {
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->cbData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        return $watcher;
    }

    /**
     * {@inheritdoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        $watcher = $this->initWatcher(Watcher::TIMER_ONCE, $callback, $options);
        // A zero interval indicates "non-repeating"
        $msInterval = 0.0;
        // ev uses full second resolution with floats so we need to
        // divide our millisecond units by 1000
        $msDelay = $msDelay/1000;
        $msInterval = $msInterval ? ($msInterval/1000) : 0.0;
        $evHandle = $this->loop->timer($msDelay, $msInterval, $this->watcherCallback, $watcher);
        $watcher->evHandle = $evHandle;
        if ($watcher->isEnabled) {
            $this->enabledNonImmediates++;
        } else {
            $evHandle->stop();
        }
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function repeat(callable $callback, $msInterval, array $options = []) {
        $watcher = $this->initWatcher(Watcher::TIMER_REPEAT, $callback, $options);
        $msDelay = isset($options["ms_delay"]) ? $options["ms_delay"] : $msInterval;
        // A zero interval indicates "non-repeating" so use the closest thing we can: 0.001
        $msInterval = ($msInterval == 0) ? 0.001 : $msInterval;
        // ev uses full second resolution with floats so we need to
        // divide our millisecond units by 1000
        $msDelay = $msDelay/1000;
        $msInterval = $msInterval ? ($msInterval/1000) : 0.0;
        $evHandle = $this->loop->timer($msDelay, $msInterval, $this->watcherCallback, $watcher);
        $watcher->evHandle = $evHandle;
        if ($watcher->isEnabled) {
            $this->enabledNonImmediates++;
        } else {
            $evHandle->stop();
        }
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        return $this->registerIo($type = Watcher::IO_READER, $stream, $callback, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        return $this->registerIo($type = Watcher::IO_WRITER, $stream, $callback, $options);
    }

    private function registerIo($type, $stream, $callback, $options) {
        $watcher = $this->initWatcher($type, $callback, $options);
        $watcher->stream = $stream;
        $events = ($type === Watcher::IO_READER) ? Ev::READ : Ev::WRITE;
        $evHandle = $this->loop->io($stream, $events, $this->watcherCallback, $watcher);
        $watcher->evHandle = $evHandle;
        if ($watcher->isEnabled) {
            $this->enabledNonImmediates++;
        } else {
            $evHandle->stop();
        }
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritDoc}
     */
    public function onSignal($signo, callable $callback, array $options = []) {
        $watcher = $this->initWatcher(Watcher::SIGNAL, $callback, $options);
        $watcher->signo = $signo;
        $evHandle = $this->loop->signal($signo, $this->watcherCallback, $watcher);
        $watcher->evHandle = $evHandle;
        if ($watcher->isEnabled) {
            $this->enabledNonImmediates++;
        } else {
            $evHandle->stop();
        }
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onError(callable $callback) {
        $this->onError = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }
        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            $watcher->isEnabled = false;
            if ($watcher->type === Watcher::IMMEDIATE) {
                unset($this->immediates[$watcherId]);
            } else {
                $watcher->evHandle->stop();
                $watcher->evHandle->clear();
                $watcher->evHandle = null;
                $this->enabledNonImmediates--;
            }
        } elseif ($watcher->type !== Watcher::IMMEDIATE) {
            $watcher->evHandle->clear();
        }
        $watcher->callback = null;
        $watcher->cbData = null;
        unset($this->watchers[$watcherId]);
    }

    /**
     * {@inheritdoc}
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
        if ($watcher->type === Watcher::IMMEDIATE) {
            unset($this->immediates[$watcherId]);
        } else {
            $watcher->evHandle->stop();
            $this->enabledNonImmediates--;
        }
    }

    /**
     * {@inheritdoc}
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
        if ($watcher->type === Watcher::IMMEDIATE) {
            $this->immediates[$watcherId] = $watcher;
        } else {
            $watcher->evHandle->start();
            $this->enabledNonImmediates++;
        }
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

    public function __destruct() {
        foreach (array_keys($this->watchers) as $watcherId) {
            $this->cancel($watcherId);
        }
        $this->watchers = [];
    }
}
