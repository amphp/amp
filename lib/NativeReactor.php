<?php

namespace Amp;

class NativeReactor implements Reactor {
    private $watchers = [];
    private $immediates = [];
    private $timerOrder = [];
    private $readStreams = [];
    private $writeStreams = [];
    private $readWatchers = [];
    private $writeWatchers = [];
    private $isTimerSortNeeded;
    private $isRunning = false;
    private $isTicking = false;
    private $onCoroutineResolution;
    private $hasExtPcntl;
    private $signalState;
    private $signalHandler;

    public function __construct() {
        $this->hasExtPcntl = \extension_loaded("pcntl");
        $this->signalState = $signalState = new \StdClass;
        $this->signalState->shouldDispatch = false;
        $this->signalState->handlers = [];
        $this->signalHandler = static function($signo) use ($signalState) {
            if (empty($signalState->handlers[$signo])) {
                return;
            }
            foreach ($signalState->handlers[$signo] as $watcherId => $watcher) {
                $out = \call_user_func($watcher->callback, $watcherId, $signo, $watcher->callbackData);
                if ($out instanceof \Generator) {
                    resolve($out)->when($this->onCoroutineResolution);
                }
            }
        };
        $this->onCoroutineResolution = static function ($error = null, $result = null) {
            if ($error) {
                throw $error;
            }
        };
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

        $this->enableTimers();
        while ($this->isRunning || $this->immediates) {
            $this->tick();
        }
    }

    private function enableTimers() {
        $now = microtime(true);
        foreach ($this->watchers as $watcherId => $watcher) {
            if (!($watcher->type & Watcher::TIMER) || isset($watcher->nextExecutionAt) || !$watcher->isEnabled) {
                continue;
            }
            $watcher->nextExecutionAt = $now + $watcher->msDelay;
            $this->timerOrder[$watcherId] = $watcher->nextExecutionAt;
        }
        $this->isTimerSortNeeded = true;
    }

    /**
     * {@inheritDoc}
     */
    public function stop() {
        $this->isRunning = $this->isTicking = false;
    }

    /**
     * {@inheritDoc}
     */
    public function tick($noWait = false) {
        $noWait = (bool) $noWait;
        $this->isTicking = true;
        if (!$this->isRunning) {
            $this->enableTimers();
        }

        if ($immediates = $this->immediates) {
            $this->doImmediates($immediates);
        }
        if ($this->signalState->shouldDispatch) {
            \pcntl_signal_dispatch();
        }

        // If an immediately watcher called stop() we pull out here
        if (!$this->isTicking) {
            return;
        }

        if ($this->immediates || $noWait) {
            $timeToNextAlarm = 0;
        } elseif (empty($this->timerOrder)) {
            $timeToNextAlarm = 1;
        } else {
            if ($this->isTimerSortNeeded) {
                \asort($this->timerOrder);
                $this->isTimerSortNeeded = false;
            }
            $nextTimerAt = \reset($this->timerOrder);
            $timeToNextAlarm = \round($nextTimerAt - \microtime(true), 4);
            $timeToNextAlarm = ($timeToNextAlarm > 0) ? $timeToNextAlarm : 0;
        }

        if ($this->readStreams || $this->writeStreams) {
            $this->selectActionableStreams($timeToNextAlarm);
        } elseif (!($this->timerOrder || $this->immediates || $this->signalState->handlers)) {
            $this->stop();
        } elseif ($timeToNextAlarm > 0) {
            \usleep($timeToNextAlarm * 1000000);
        }

        if ($this->timerOrder || $this->immediates) {
            $this->executeTimers();
        }

        $this->isTicking = false;
    }

    private function doImmediates($immediates) {
        foreach ($immediates as $watcherId => $watcher) {
            try {
                unset(
                    $this->immediates[$watcherId],
                    $this->watchers[$watcherId]
                );
                $result = \call_user_func($watcher->callback, $watcherId, $watcher->callbackData);
            } catch (\Throwable $e) {
                \call_user_func($this->onCoroutineResolution, $e);
                continue;
            } catch (\Exception $e) {
                \call_user_func($this->onCoroutineResolution, $e);
                continue;
            }

            if ($result instanceof \Generator) {
                resolve($result)->when($this->onCoroutineResolution);
            }
        }
    }

    private function selectActionableStreams($timeout) {
        $r = $this->readStreams;
        $w = $this->writeStreams;
        $e = null;

        if ($timeout <= 0) {
            $sec = 0;
            $usec = 0;
        } else {
            $sec = floor($timeout);
            $usec = ($timeout - $sec) * 1000000;
        }
        if (!@stream_select($r, $w, $e, $sec, $usec)) {
            return;
        }
        foreach ($r as $stream) {
            $streamId = (int) $stream;
            foreach ($this->readWatchers[$streamId] as $watcherId => $watcher) {
                $this->doIoCallback($watcherId, $watcher, $stream);
            }
        }
        foreach ($w as $stream) {
            $streamId = (int) $stream;
            foreach ($this->writeWatchers[$streamId] as $watcherId => $watcher) {
                $this->doIoCallback($watcherId, $watcher, $stream);
            }
        }
    }

    private function doIoCallback($watcherId, $watcher, $stream) {
        try {
            $result = \call_user_func($watcher->callback, $watcherId, $stream, $watcher->callbackData);
            if ($result instanceof \Generator) {
                resolve($result)->when($this->onCoroutineResolution);
            }
        } catch (\Throwable $e) {
            \call_user_func($this->onCoroutineResolution, $e);
        } catch (\Exception $e) {
            \call_user_func($this->onCoroutineResolution, $e);
        }
    }

    private function executeTimers() {
        $now = microtime(true);
        if ($this->isTimerSortNeeded) {
            \asort($this->timerOrder);
            $this->isTimerSortNeeded = false;
        }

        foreach ($this->timerOrder as $watcherId => $executionCutoff) {
            if ($executionCutoff > $now) {
                break;
            }
            if (!isset($this->watchers[$watcherId])) {
                unset($this->timerOrder[$watcherId]);
                continue;
            }

            $watcher = $this->watchers[$watcherId];

            $result = \call_user_func($watcher->callback, $watcherId, $watcher->callbackData);
            if ($result instanceof \Generator) {
                resolve($result)->when($this->onCoroutineResolution);
            }

            if ($watcher->type === Watcher::TIMER_ONCE) {
                unset(
                    $this->watchers[$watcherId],
                    $this->timerOrder[$watcherId]
                );
                continue;
            } elseif ($watcher->isEnabled) {
                $this->isTimerSortNeeded = true;
                $watcher->nextExecutionAt = $now + $watcher->msInterval;
                $this->timerOrder[$watcherId] = $watcher->nextExecutionAt;
            } else {
                unset($this->timerOrder[$watcherId]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function immediately(callable $callback, array $options = []) {
        $watcher = new Watcher;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::IMMEDIATE;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;

        if ($watcher->isEnabled) {
            $this->watchers[$watcherId] = $this->immediates[$watcherId] = $watcher;
        }

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, $msDelay, array $options = []) {
        $msDelay = (int) $msDelay;
        assert(($msDelay >= 0), "\$msDelay at Argument 2 expects integer >= 0");

        /* In the php7 branch we use an anonymous class with Struct for this.
         * Using a stdclass isn't terribly readable and it's prone to error but
         * it's the easiest way to minimize the distance between 5.x and 7 code
         * and keep maintenance simple.
         */
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::TIMER_ONCE;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->msDelay = round(($msDelay / 1000), 3);
        $watcher->nextExecutionAt = null;

        if ($watcher->isEnabled && $this->isRunning) {
            $nextExecutionAt = microtime(true) + $watcher->msDelay;
            $watcher->nextExecutionAt = $nextExecutionAt;
            $this->timerOrder[$watcherId] = $nextExecutionAt;
            $this->isTimerSortNeeded = true;
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function repeat(callable $callback, $msInterval, array $options = []) {
        $msInterval = (int) $msInterval;
        assert(($msInterval >= 0), "\$msInterval at Argument 2 expects integer >= 0");
        $msDelay = isset($options["ms_delay"]) ? $options["ms_delay"] : (int) $msInterval;
        assert(($msDelay >= 0), "ms_delay option expects integer >= 0");

        /* In the php7 branch we use an anonymous class with Struct for this.
         * Using a stdclass isn't terribly readable and it's prone to error but
         * it's the easiest way to minimize the distance between 5.x and 7 code
         * and keep maintenance simple.
         */
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::TIMER_REPEAT;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->msInterval = round(($msInterval / 1000), 3);
        $watcher->msDelay = round(($msDelay / 1000), 3);
        $watcher->nextExecutionAt = null; // only needed for php5.x

        if ($watcher->isEnabled && $this->isRunning) {
            $increment = (isset($watcher->msDelay) ? $watcher->msDelay : $watcher->msInterval);
            $nextExecutionAt = microtime(true) + $increment;
            $this->timerOrder[$watcherId] = $watcher->nextExecutionAt = $nextExecutionAt;
            $this->isTimerSortNeeded = true;
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, array $options = []) {
        return $this->registerIoWatcher($stream, $callback, $options, Watcher::IO_READER);
    }

    /**
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, array $options = []) {
        return $this->registerIoWatcher($stream, $callback, $options, Watcher::IO_WRITER);
    }

    private function registerIoWatcher($stream, $callback, $options, $type) {
        /* In the php7 branch we use an anonymous class with Struct for this.
         * Using a stdclass isn't terribly readable and it's prone to error but
         * it's the easiest way to minimize the distance between 5.x and 7 code
         * and keep maintenance simple.
         */
        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = $type;
        $watcher->callback = $callback;
        $watcher->callbackData = @$options["cb_data"];
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->stream = $stream;
        $watcher->streamId = $streamId = (int) $stream;

        if ($watcher->isEnabled) {
            if ($type === Watcher::IO_READER) {
                $this->readStreams[$streamId] = $stream;
                $this->readWatchers[$streamId][$watcherId] = $watcher;
            } else {
                $this->writeStreams[$streamId] = $stream;
                $this->writeWatchers[$streamId][$watcherId] = $watcher;
            }
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     * @throws \RuntimeException if ext/pcntl unavailable or signal handler registration fails
     */
    public function onSignal($signo, callable $func, array $options = []) {
        if (empty($this->hasExtPcntl)) {
            throw new \RuntimeException(
                "Cannot react to signals; ext/pcntl not loaded"
            );
        }

        $signo = (int) $signo;

        $watcher = new \StdClass;
        $watcher->id = $watcherId = \spl_object_hash($watcher);
        $watcher->type = Watcher::SIGNAL;
        $watcher->callback = $func;
        $watcher->callbackData = isset($options["cb_data"]) ? $options["cb_data"] : null;
        $watcher->isEnabled = isset($options["enable"]) ? (bool) $options["enable"] : true;
        $watcher->signo = $signo;

        if ($watcher->isEnabled) {
            if (empty($this->signalState->handlers[$signo]) && !@\pcntl_signal($signo, $this->signalHandler)) {
                throw new \RuntimeException(
                    "Failed registering signal handler"
                );
            }
            $this->signalState->shouldDispatch = true;
            $this->signalState->handlers[$signo][$watcherId] = $watcher;
        }

        $this->watchers[$watcherId] = $watcher;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function onError(callable $func) {
        $this->onCoroutineResolution = static function($e = null, $r = null) use ($func) {
            if ($e) {
                \call_user_func($func, $e);
            }
        };
    }

    /**
     * {@inheritDoc}
     */
    public function cancel($watcherId) {
        $this->disable($watcherId);
        unset(
            $this->watchers[$watcherId],
            $this->timerOrder[$watcherId]
        );
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
            // If the watcher is already enabled we're finished here
            return;
        }

        $watcher->isEnabled = true;

        switch ($watcher->type) {
            case Watcher::TIMER_ONCE:
            case Watcher::TIMER_REPEAT:
                if (!isset($watcher->nextExecutionAt)) {
                    $watcher->nextExecutionAt = microtime(true) + $watcher->msDelay;
                }
                $this->isTimerSortNeeded = true;
                $this->timerOrder[$watcherId] = $watcher->nextExecutionAt;
                break;
            case Watcher::IO_READER:
                $streamId = (int) $watcher->stream;
                $this->readStreams[$streamId] = $watcher->stream;
                $this->readWatchers[$streamId][$watcherId] = $watcher;
                break;
            case Watcher::IO_WRITER:
                $streamId = (int) $watcher->stream;
                $this->writeStreams[$streamId] = $watcher->stream;
                $this->writeWatchers[$streamId][$watcherId] = $watcher;
                break;
            case Watcher::IMMEDIATE:
                $this->immediates[$watcherId] = $watcher;
                break;
            case Watcher::SIGNAL:
                $signo = $watcher->signo;
                if (empty($this->signalState->handlers[$signo])) {
                    \pcntl_signal($signo, $this->signalHandler);
                }
                $this->signalState->handlers[$signo][$watcherId] = $watcher;
                $this->signalState->shouldDispatch = true;
                break;
        }
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

        switch ($watcher->type) {
            case Watcher::TIMER_ONCE:
            case Watcher::TIMER_REPEAT:
                unset($this->timerOrder[$watcherId]);
                break;
            case Watcher::IO_READER:
                $streamId = $watcher->streamId;
                unset($this->readWatchers[$streamId][$watcherId]);
                if (empty($this->readWatchers[$streamId])) {
                    unset($this->readStreams[$streamId]);
                }
                break;
            case Watcher::IO_WRITER:
                $streamId = $watcher->streamId;
                unset($this->writeWatchers[$streamId][$watcherId]);
                if (empty($this->writeWatchers[$streamId])) {
                    unset($this->writeStreams[$streamId]);
                }
                break;
            case Watcher::IMMEDIATE:
                unset($this->immediates[$watcherId]);
                break;
            case Watcher::SIGNAL:
                $signo = $watcher->signo;
                unset($this->signalState->handlers[$signo][$watcherId]);
                if (empty($this->signalState->handlers[$signo])) {
                    unset($this->signalState->handlers[$signo]);
                    \pcntl_signal($signo, \SIG_DFL);
                    if (empty($this->signalState->handlers)) {
                        $this->signalState->shouldDispatch = false;
                    }
                }
                break;
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

    public function __debugInfo() {
        return $this->info();
    }
}
