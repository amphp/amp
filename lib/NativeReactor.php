<?php

namespace Amp;

class NativeReactor implements Reactor {
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
    private $lastWatcherId = "a";
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
     * {@inheritDoc}
     */
    public function stop() {
        $this->isRunning = $this->isTicking = false;
    }

    /**
     * {@inheritDoc}
     */
    public function tick(bool $noWait = false) {
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
                        resolve($result, $this)->when($this->onCallbackResolution);
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
                        resolve($result, $this)->when($this->onCallbackResolution);
                    }
                }
            }
            foreach ($w as $writableStream) {
                $streamId = (int) $writableStream;
                foreach ($this->writeCallbacks[$streamId] as $watcherId => $callback) {
                    $result = $callback($this, $watcherId, $writableStream);
                    if ($result instanceof \Generator) {
                        resolve($result, $this)->when($this->onCallbackResolution);
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
                resolve($result, $this)->when($this->onCallbackResolution);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function at(callable $callback, $unixTimeOrStr): string {
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
     * {@inheritDoc}
     */
    public function immediately(callable $callback): string {
        $watcherId = $this->lastWatcherId++;
        $this->immediates[$watcherId] = $callback;

        return $watcherId;
    }

    /**
     * {@inheritDoc}
     */
    public function once(callable $callback, int $msDelay): string {
        return $this->scheduleAlarm($callback, $msDelay, $isRepeating = false);
    }

    /**
     * {@inheritDoc}
     */
    public function repeat(callable $callback, int $msDelay): string {
        return $this->scheduleAlarm($callback, $msDelay, $isRepeating = true);
    }

    private function scheduleAlarm(callable $callback, int $msDelay, bool $isRepeating): string {
        $watcherId = $this->lastWatcherId++;
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
     * {@inheritDoc}
     */
    public function onReadable($stream, callable $callback, bool $enableNow = true): string {
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
     * {@inheritDoc}
     */
    public function onWritable($stream, callable $callback, bool $enableNow = true): string {
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
     * {@inheritDoc}
     */
    public function cancel(string $watcherId) {
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

    private function cancelReadWatcher(string $watcherId) {
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

    private function cancelWriteWatcher(string $watcherId) {
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
     * {@inheritDoc}
     */
    public function enable(string $watcherId) {
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
     * {@inheritDoc}
     */
    public function disable(string $watcherId) {
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
     * {@inheritDoc}
     */
    public function onError(callable $func) {
        $this->onError = $func;
    }
}
