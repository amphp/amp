<?php

namespace Amp;

class NativeReactor extends CoroutineResolver implements Reactor {
    private $alarms = [];
    private $immediates = [];
    private $alarmOrder = [];
    private $readStreams = [];
    private $writeStreams = [];
    private $readCallbacks = [];
    private $writeCallbacks = [];
    private $watcherIdReadStreamIdMap = [];
    private $watcherIdWriteStreamIdMap = [];
    private $disabledWatchers = [];
    private $resolution = 1000;
    private $lastWatcherId = 1;
    private $isRunning = false;
    private $isTicking = false;
    private $onError;
    private $onCallbackResolution;

    private static $instanceCount = 0;

    private static $DISABLED_ALARM = 0;
    private static $DISABLED_READ = 1;
    private static $DISABLED_WRITE = 2;
    private static $DISABLED_IMMEDIATE = 3;
    private static $MICROSECOND = 1000000;

    public function __construct() {
        self::$instanceCount++;
        $this->onCallbackResolution = function($e = null, $r = null) {
            if (empty($e)) {
                return;
            } elseif ($onError = $this->onError) {
                $onError($e);
            } else {
                throw $e;
            }
        };
    }

    public function __debugInfo() {
        return [
            'timers'            => count($this->alarms),
            'immediates'        => count($this->immediates),
            'io_readers'        => count($this->readStreams),
            'io_writers'        => count($this->writeStreams),
            'disabled'          => count($this->disabledWatchers),
            'last_watcher_id'   => $this->lastWatcherId,
            'instances'         => self::$instanceCount,
        ];
    }

    public function __destruct() {
        self::$instanceCount--;
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

        $this->enableAlarms();
        while ($this->isRunning || $this->immediates) {
            $this->tick();
        }
    }

    private function enableAlarms() {
        $now = microtime(true);
        foreach ($this->alarms as $watcherId => $alarmStruct) {
            $nextExecution = $alarmStruct[1];
            if (!$nextExecution) {
                $delay = $alarmStruct[2];
                $nextExecution = $now + $delay;
                $alarmStruct[1] = $nextExecution;
                $this->alarms[$watcherId] = $alarmStruct;
                $this->alarmOrder[$watcherId] = $nextExecution;
            }
        }
    }

    /**
     * Stop the event reactor
     *
     * @return void
     */
    public function stop() {
        $this->isRunning = $this->isTicking = false;
    }

    /**
     * Execute a single event loop iteration
     *
     * @param bool $noWait If TRUE, return immediately when no watchers are immediately ready to trigger
     * @return void
     */
    public function tick($noWait = false) {
        try {
            $this->isTicking = true;
            if (!$this->isRunning) {
                $this->enableAlarms();
            }

            if ($immediates = $this->immediates) {
                $this->immediates = [];
                foreach ($immediates as $watcherId => $callback) {
                    $result = $callback($this, $watcherId);
                    if ($result instanceof \Generator) {
                        $this->coroutine($result)->when($this->onCallbackResolution);
                    }
                }
            }

            // If an immediately watcher called stop() then pull out here
            if (!$this->isTicking) {
                return;
            }

            if ($this->immediates) {
                $timeToNextAlarm = 0;
            } elseif ($this->alarmOrder) {
                $timeToNextAlarm = $noWait ? 0 : round(min($this->alarmOrder) - microtime(true), 4);
            } else {
                $timeToNextAlarm = $noWait ? 0 : 1;
            }


            if ($this->readStreams || $this->writeStreams) {
                $this->selectActionableStreams($timeToNextAlarm);
            } elseif (!($this->alarmOrder || $this->immediates)) {
                $this->stop();
            } elseif ($timeToNextAlarm > 0) {
                usleep($timeToNextAlarm * self::$MICROSECOND);
            }

            if ($this->alarmOrder) {
                $this->executeAlarms();
            }
            $this->isTicking = false;
        } catch (\Exception $error) {
            $errorHandler = $this->onCallbackResolution;
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
            $usec = ($timeout - $sec) * self::$MICROSECOND;
        }

        if (@stream_select($r, $w, $e, $sec, $usec)) {
            foreach ($r as $readableStream) {
                $streamId = (int) $readableStream;
                foreach ($this->readCallbacks[$streamId] as $watcherId => $callback) {
                    $result = $callback($this, $watcherId, $readableStream);
                    if ($result instanceof \Generator) {
                        $this->coroutine($result)->when($this->onCallbackResolution);
                    }
                }
            }
            foreach ($w as $writableStream) {
                $streamId = (int) $writableStream;
                foreach ($this->writeCallbacks[$streamId] as $watcherId => $callback) {
                    $result = $callback($this, $watcherId, $writableStream);
                    if ($result instanceof \Generator) {
                        $this->coroutine($result)->when($this->onCallbackResolution);
                    }
                }
            }
        }
    }

    private function executeAlarms() {
        $now = microtime(true);
        asort($this->alarmOrder);

        foreach ($this->alarmOrder as $watcherId => $executionCutoff) {
            if ($executionCutoff > $now) {
                break;
            }

            list($callback, $nextExecution, $interval, $isRepeating) = $this->alarms[$watcherId];

            if ($isRepeating) {
                $nextExecution += $interval;
                $this->alarms[$watcherId] = [$callback, $nextExecution, $interval, $isRepeating];
                $this->alarmOrder[$watcherId] = $nextExecution;
            } else {
                unset(
                    $this->alarms[$watcherId],
                    $this->alarmOrder[$watcherId]
                );
            }

            $result = $callback($this, $watcherId);
            if ($result instanceof \Generator) {
                $this->coroutine($result)->when($this->onCallbackResolution);
            }
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
     * Schedule a callback for immediate invocation in the next event loop iteration
     *
     * @param callable $callback Any valid PHP callable
     * @return string Returns a unique watcher ID
     */
    public function immediately(callable $callback) {
        $watcherId = (string) $this->lastWatcherId++;
        $this->immediates[$watcherId] = $callback;

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
        return $this->scheduleAlarm($callback, $msDelay, $isRepeating = false);
    }

    /**
     * Schedule a recurring callback to execute every $interval seconds until cancelled
     *
     * @param callable $callback Any valid PHP callable
     * @param int $msDelay The interval in milliseconds between callback invocations
     * @return string Returns a unique watcher ID
     */
    public function repeat(callable $callback, $msDelay) {
        return $this->scheduleAlarm($callback, $msDelay, $isRepeating = true);
    }

    private function scheduleAlarm($callback, $msDelay, $isRepeating) {
        $watcherId = (string) $this->lastWatcherId++;
        $msDelay = round(($msDelay / $this->resolution), 3);

        if ($this->isRunning) {
            $nextExecution = (microtime(true) + $msDelay);
            $this->alarmOrder[$watcherId] = $nextExecution;
        } else {
            $nextExecution = null;
        }

        $alarmStruct = [$callback, $nextExecution, $msDelay, $isRepeating];
        $this->alarms[$watcherId] = $alarmStruct;

        return $watcherId;
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
        $watcherId = (string) $this->lastWatcherId++;

        if ($enableNow) {
            $streamId = (int) $stream;
            $this->readStreams[$streamId] = $stream;
            $this->readCallbacks[$streamId][$watcherId] = $callback;
            $this->watcherIdReadStreamIdMap[$watcherId] = $streamId;
        } else {
            $this->disabledWatchers[$watcherId] = [self::$DISABLED_READ, [$stream, $callback]];
        }

        return $watcherId;
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
        $watcherId = (string) $this->lastWatcherId++;

        if ($enableNow) {
            $streamId = (int) $stream;
            $this->writeStreams[$streamId] = $stream;
            $this->writeCallbacks[$streamId][$watcherId] = $callback;
            $this->watcherIdWriteStreamIdMap[$watcherId] = $streamId;
        } else {
            $this->disabledWatchers[$watcherId] = [self::$DISABLED_WRITE, [$stream, $callback]];
        }

        return $watcherId;
    }

    /**
     * Cancel an existing watcher
     *
     * @param string $watcherId
     * @return void
     */
    public function cancel($watcherId) {
        if (isset($this->alarms[$watcherId])) {
            unset(
                $this->alarms[$watcherId],
                $this->alarmOrder[$watcherId]
            );
        } elseif (isset($this->watcherIdReadStreamIdMap[$watcherId])) {
            $this->cancelReadWatcher($watcherId);
        } elseif (isset($this->watcherIdWriteStreamIdMap[$watcherId])) {
            $this->cancelWriteWatcher($watcherId);
        } elseif (isset($this->disabledWatchers[$watcherId])) {
            unset($this->disabledWatchers[$watcherId]);
        } elseif (isset($this->immediates[$watcherId])) {
            unset($this->immediates[$watcherId]);
        }
    }

    private function cancelReadWatcher($watcherId) {
        $streamId = $this->watcherIdReadStreamIdMap[$watcherId];

        unset(
            $this->readCallbacks[$streamId][$watcherId],
            $this->watcherIdReadStreamIdMap[$watcherId],
            $this->disabledWatchers[$watcherId]
        );

        if (empty($this->readCallbacks[$streamId])) {
            unset($this->readStreams[$streamId]);
        }
    }

    private function cancelWriteWatcher($watcherId) {
        $streamId = $this->watcherIdWriteStreamIdMap[$watcherId];

        unset(
            $this->writeCallbacks[$streamId][$watcherId],
            $this->watcherIdWriteStreamIdMap[$watcherId],
            $this->disabledWatchers[$watcherId]
        );

        if (empty($this->writeCallbacks[$streamId])) {
            unset($this->writeStreams[$streamId]);
        }
    }

    /**
     * Enable a disabled timer/stream watcher
     *
     * @param string $watcherId
     * @return void
     */
    public function enable($watcherId) {
        if (!isset($this->disabledWatchers[$watcherId])) {
            return;
        }

        list($type, $watcherStruct) = $this->disabledWatchers[$watcherId];

        unset($this->disabledWatchers[$watcherId]);

        switch ($type) {
            case self::$DISABLED_ALARM:
                if (!$nextExecution = $watcherStruct[1]) {
                    $nextExecution = microtime(true) + $watcherStruct[2];
                    $watcherStruct[1] = $nextExecution;
                }
                $this->alarms[$watcherId] = $watcherStruct;
                $this->alarmOrder[$watcherId] = $nextExecution;
                break;
            case self::$DISABLED_READ:
                list($stream, $callback) = $watcherStruct;
                $streamId = (int) $stream;
                $this->readCallbacks[$streamId][$watcherId] = $callback;
                $this->watcherIdReadStreamIdMap[$watcherId] = $streamId;
                $this->readStreams[$streamId] = $stream;
                break;
            case self::$DISABLED_WRITE:
                list($stream, $callback) = $watcherStruct;
                $streamId = (int) $stream;
                $this->writeCallbacks[$streamId][$watcherId] = $callback;
                $this->watcherIdWriteStreamIdMap[$watcherId] = $streamId;
                $this->writeStreams[$streamId] = $stream;
                break;
            case self::$DISABLED_IMMEDIATE:
                $this->immediates[$watcherId] = $watcherStruct;
                break;
        }
    }

    /**
     * Temporarily disable (but don't cancel) an existing timer/stream watcher
     *
     * @param string $watcherId
     * @return void
     */
    public function disable($watcherId) {
        if (isset($this->alarms[$watcherId])) {
            $alarmStruct = $this->alarms[$watcherId];
            $this->disabledWatchers[$watcherId] = [self::$DISABLED_ALARM, $alarmStruct];
            unset(
                $this->alarms[$watcherId],
                $this->alarmOrder[$watcherId]
            );
        } elseif (isset($this->watcherIdReadStreamIdMap[$watcherId])) {
            $streamId = $this->watcherIdReadStreamIdMap[$watcherId];
            $stream = $this->readStreams[$streamId];
            $callback = $this->readCallbacks[$streamId][$watcherId];

            unset(
                $this->readCallbacks[$streamId][$watcherId],
                $this->watcherIdReadStreamIdMap[$watcherId]
            );

            if (empty($this->readCallbacks[$streamId])) {
                unset($this->readStreams[$streamId]);
            }

            $this->disabledWatchers[$watcherId] = [self::$DISABLED_READ, [$stream, $callback]];

        } elseif (isset($this->watcherIdWriteStreamIdMap[$watcherId])) {
            $streamId = $this->watcherIdWriteStreamIdMap[$watcherId];
            $stream = $this->writeStreams[$streamId];
            $callback = $this->writeCallbacks[$streamId][$watcherId];

            unset(
                $this->writeCallbacks[$streamId][$watcherId],
                $this->watcherIdWriteStreamIdMap[$watcherId]
            );

            if (empty($this->writeCallbacks[$streamId])) {
                unset($this->writeStreams[$streamId]);
            }

            $this->disabledWatchers[$watcherId] = [self::$DISABLED_WRITE, [$stream, $callback]];

        } elseif (isset($this->immediates[$watcherId])) {
            $this->disabledWatchers[$watcherId] =  [self::$DISABLED_IMMEDIATE, $this->immediates[$watcherId]];
            unset($this->immediates[$watcherId]);
        }
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
}
