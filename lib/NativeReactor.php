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
    private $lastWatcherId = "a";
    private $isRunning = false;
    private $isTicking = false;
    private $onError;
    private $onCoroutineResolution;
    private static $instanceCount = 0;

    public function __construct() {
        self::$instanceCount++;
        $this->onCoroutineResolution = function($e = null, $r = null) {
            if (empty($e)) {
                return;
            } elseif ($this->onError) {
                call_user_func($this->onError, $e);
            } else {
                throw $e;
            }
        };
    }

    public function __destruct() {
        self::$instanceCount--;
    }

    public function __debugInfo() {
        $timers = $immediates = $readers = $writers = $disabled = 0;
        foreach ($this->watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::TIMER_ONCE:
                    // fallthrough
                case Watcher::TIMER_REPEAT:
                    if ($watcher->isEnabled) { $timers++; } else { $disabled++; }
                    break;
                case Watcher::IO_READER:
                    if ($watcher->isEnabled) { $readers++; } else { $disabled++; }
                    break;
                case Watcher::IO_WRITER:
                    if ($watcher->isEnabled) { $writers++; } else { $disabled++; }
                    break;
                case Watcher::IMMEDIATE:
                    if ($watcher->isEnabled) { $immediates++; } else { $disabled++; }
                    break;
            }
        }

        return [
            'timers'            => $timers,
            'immediates'        => $immediates,
            'io_readers'        => $readers,
            'io_writers'        => $writers,
            'disabled'          => $disabled,
            'last_watcher_id'   => $this->lastWatcherId,
            'instances'         => self::$instanceCount,
        ];
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
        try {
            $noWait = (bool) $noWait;
            $this->isTicking = true;
            if (!$this->isRunning) {
                $this->enableTimers();
            }

            if ($immediates = $this->immediates) {
                foreach ($immediates as $watcherId => $watcher) {
                    unset(
                        $this->immediates[$watcherId],
                        $this->watchers[$watcherId]
                    );
                    $result = call_user_func($watcher->callback, $this, $watcherId, $watcher->callbackData);
                    if ($result instanceof \Generator) {
                        resolve($result, $this)->when($this->onCoroutineResolution);
                    }
                }
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
                    asort($this->timerOrder);
                    $this->isTimerSortNeeded = false;
                }
                // This reset() is important ... don't remove it!
                $nextTimerAt = reset($this->timerOrder);
                $timeToNextAlarm = round($nextTimerAt - microtime(true), 4);
                $timeToNextAlarm = ($timeToNextAlarm > 0) ? $timeToNextAlarm : 0;
            }

            if ($this->readStreams || $this->writeStreams) {
                $this->selectActionableStreams($timeToNextAlarm);
            } elseif (!($this->timerOrder || $this->immediates)) {
                $this->stop();
            } elseif ($timeToNextAlarm > 0) {
                usleep($timeToNextAlarm * 1000000);
            }

            if ($this->timerOrder || $this->immediates) {
                $this->executeTimers();
            }
            $this->isTicking = false;
        } catch (\Exception $error) {
            $errorHandler = $this->onCoroutineResolution;
            $errorHandler($error);
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

        if (@stream_select($r, $w, $e, $sec, $usec)) {
            foreach ($r as $readableStream) {
                $streamId = (int) $readableStream;
                foreach ($this->readWatchers[$streamId] as $watcherId => $watcher) {
                    $result = call_user_func($watcher->callback, $this, $watcherId, $readableStream, $watcher->callbackData);
                    if ($result instanceof \Generator) {
                        resolve($result, $this)->when($this->onCoroutineResolution);
                    }
                }
            }
            foreach ($w as $writableStream) {
                $streamId = (int) $writableStream;
                foreach ($this->writeWatchers[$streamId] as $watcherId => $watcher) {
                    $result = call_user_func($watcher->callback, $this, $watcherId, $writableStream, $watcher->callbackData);
                    if ($result instanceof \Generator) {
                        resolve($result, $this)->when($this->onCoroutineResolution);
                    }
                }
            }
        }
    }

    private function executeTimers() {
        $now = microtime(true);
        if ($this->isTimerSortNeeded) {
            asort($this->timerOrder);
            $this->isTimerSortNeeded = false;
        }

        foreach ($this->timerOrder as $watcherId => $executionCutoff) {
            if ($executionCutoff > $now) {
                break;
            }

            $watcher = $this->watchers[$watcherId];

            $result = call_user_func($watcher->callback, $this, $watcherId, $watcher->callbackData);
            if ($result instanceof \Generator) {
                resolve($result, $this)->when($this->onCoroutineResolution);
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
        $watcher->id = $watcherId = $this->lastWatcherId++;
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
        $watcher->id = $watcherId = $this->lastWatcherId++;
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
        $watcher->id = $watcherId = $this->lastWatcherId++;
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
        $watcher->id = $watcherId = $this->lastWatcherId++;
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
            default:
                assert(false, "Unexpected Watcher type constant encountered");
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
            default:
                assert(false, "Unexpected Watcher type constant encountered");
        }
    }

    /**
     * {@inheritDoc}
     */
    public function onError(callable $func) {
        $this->onError = $func;
    }
}