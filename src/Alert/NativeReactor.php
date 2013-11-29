<?php

namespace Alert;

class NativeReactor implements Reactor {

    private $alarms = [];
    private $alarmOrder = [];
    private $readStreams = [];
    private $writeStreams = [];
    private $readCallbacks = [];
    private $writeCallbacks = [];
    private $watcherIdReadStreamIdMap = [];
    private $watcherIdWriteStreamIdMap = [];
    private $disabledWatchers = array();
    private $microsecondResolution = 1000000;
    private $lastWatcherId;
    private $isRunning = FALSE;

    private static $DISABLED_ALARM = 0;
    private static $DISABLED_READ = 1;
    private static $DISABLED_WRITE = 2;

    function __construct() {
        $this->lastWatcherId = PHP_INT_MAX * -1;
    }

    function run() {
        if (!$this->isRunning) {
            $this->isRunning = TRUE;
            $this->enableAlarms();
            while ($this->isRunning) {
                $this->tick();
            }
        }
    }

    private function enableAlarms() {
        $now = microtime(TRUE);
        $enabled = 0;
        foreach ($this->alarms as $watcherId => $alarmStruct) {
            $nextExecution = $alarmStruct[1];
            if (!$nextExecution) {
                $enabled++;
                $delay = $alarmStruct[2];
                $nextExecution = $now + $delay;
                $alarmStruct[1] = $nextExecution;
                $this->alarms[$watcherId] = $alarmStruct;
                $this->alarmOrder[$watcherId] = $nextExecution;
            }
        }
    }

    function stop() {
        $this->isRunning = FALSE;
    }

    function tick() {
        if (!$this->isRunning) {
            $this->enableAlarms();
        }

        $timeToNextAlarm = $this->alarmOrder
            ? round(min($this->alarmOrder) - microtime(TRUE), 4)
            : 1;

        if ($this->readStreams || $this->writeStreams) {
            $this->selectActionableStreams($timeToNextAlarm);
        } elseif ($timeToNextAlarm > 0) {
            usleep($timeToNextAlarm * $this->microsecondResolution);
        }

        if ($this->alarmOrder) {
            $this->executeAlarms();
        }
    }

    private function selectActionableStreams($timeout) {
        $r = $this->readStreams;
        $w = $this->writeStreams;
        $e = NULL;

        if ($timeout <= 0) {
            $sec = 0;
            $usec = 0;
        } else {
            $sec = floor($timeout);
            $usec = ($timeout - $sec) * $this->microsecondResolution;
        }

        if (stream_select($r, $w, $e, $sec, $usec)) {
            foreach ($r as $readableStream) {
                $streamId = (int) $readableStream;
                foreach ($this->readCallbacks[$streamId] as $watcherId => $callback) {
                    $callback($watcherId, $readableStream, $this);
                }
            }
            foreach ($w as $writableStream) {
                $streamId = (int) $writableStream;
                foreach ($this->writeCallbacks[$streamId] as $watcherId => $callback) {
                    $callback($watcherId, $writableStream, $this);
                }
            }
        }
    }

    private function executeAlarms() {
        $now = microtime(TRUE);

        asort($this->alarmOrder);

        foreach ($this->alarmOrder as $watcherId => $executionCutoff) {
            if ($executionCutoff <= $now) {
                $this->doAlarmCallback($watcherId);
            } else {
                break;
            }
        }
    }

    private function doAlarmCallback($watcherId) {
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

        $callback($watcherId, $this);
    }

    function at(callable $callback, $timeString) {
        $now = time();
        $executeAt = @strtotime($timeString);

        if ($executeAt === FALSE && $executeAt <= $now) {
            throw new \InvalidArgumentException(
                'Valid future time string (parsable by strtotime()) required'
            );
        }

        $delay = $executeAt - $now;

        return $this->once($callback, $delay);
    }

    function immediately(callable $callback) {
        return $this->scheduleAlarm($callback, $delay = 0, $isRepeating = FALSE);
    }

    function once(callable $callback, $delay) {
        return $this->scheduleAlarm($callback, $delay, $isRepeating = FALSE);
    }

    function repeat(callable $callback, $interval) {
        return $this->scheduleAlarm($callback, $interval, $isRepeating = TRUE);
    }

    private function scheduleAlarm($callback, $delay, $isRepeating) {
        $watcherId = ++$this->lastWatcherId;

        if ($this->isRunning) {
            $nextExecution = (microtime(TRUE) + $delay);
            $this->alarmOrder[$watcherId] = $nextExecution;
        } else {
            $nextExecution = NULL;
        }

        $alarmStruct = [$callback, $nextExecution, $delay, $isRepeating];
        $this->alarms[$watcherId] = $alarmStruct;

        return $watcherId;
    }

    function onReadable($stream, callable $callback, $enableNow = TRUE) {
        $watcherId = ++$this->lastWatcherId;

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

    function onWritable($stream, callable $callback, $enableNow = TRUE) {
        $watcherId = ++$this->lastWatcherId;

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

    function cancel($watcherId) {
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

    function enable($watcherId) {
        if (!isset($this->disabledWatchers[$watcherId])) {
            return;
        }

        list($type, $watcherStruct) = $this->disabledWatchers[$watcherId];

        unset($this->disabledWatchers[$watcherId]);

        switch ($type) {
            case self::$DISABLED_ALARM:
                if (!$nextExecution = $watcherStruct[1]) {
                    $nextExecution = microtime(TRUE) + $watcherStruct[2];
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
        }
    }

    function disable($watcherId) {
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
        }
    }

}
