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
    private $resolution = 1000;
    private $lastWatcherId = 0;
    private $isRunning = false;

    private static $DISABLED_ALARM = 0;
    private static $DISABLED_READ = 1;
    private static $DISABLED_WRITE = 2;
    private static $MICROSECOND = 1000000;

    public function run(callable $onStart = NULL) {
        if ($this->isRunning) {
            return;
        }

        $this->isRunning = true;
        if ($onStart) {
            $this->immediately(function() use ($onStart) { $onStart($this); });
        }
        $this->enableAlarms();
        while ($this->isRunning) {
            $this->tick();
        }
    }

    private function enableAlarms() {
        $now = microtime(true);
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

    public function stop() {
        $this->isRunning = false;
    }

    public function tick() {
        if (!$this->isRunning) {
            $this->enableAlarms();
        }

        $timeToNextAlarm = $this->alarmOrder
            ? round(min($this->alarmOrder) - microtime(true), 4)
            : 1;

        if ($this->readStreams || $this->writeStreams) {
            $this->selectActionableStreams($timeToNextAlarm);
        } elseif (!$this->alarmOrder) {
            $this->stop();
        } elseif ($timeToNextAlarm > 0) {
            usleep($timeToNextAlarm * self::$MICROSECOND);
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
            $usec = ($timeout - $sec) * self::$MICROSECOND;
        }

        if (@stream_select($r, $w, $e, $sec, $usec)) {
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
        $now = microtime(true);

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

    public function at(callable $callback, $timeString) {
        $now = time();
        $executeAt = @strtotime($timeString);

        if ($executeAt === false || $executeAt <= $now) {
            throw new \InvalidArgumentException(
                'Valid future time string (parsable by strtotime()) required'
            );
        }

        $msDelay = ($executeAt - $now) * $this->resolution;

        return $this->once($callback, $msDelay);
    }

    public function immediately(callable $callback) {
        return $this->scheduleAlarm($callback, $delay = 0, $isRepeating = false);
    }

    public function once(callable $callback, $delay) {
        return $this->scheduleAlarm($callback, $delay, $isRepeating = false);
    }

    public function repeat(callable $callback, $interval) {
        return $this->scheduleAlarm($callback, $interval, $isRepeating = true);
    }

    private function scheduleAlarm($callback, $delay, $isRepeating) {
        $watcherId = $this->lastWatcherId++;
        $delay = round(($delay / $this->resolution), 3);

        if ($this->isRunning) {
            $nextExecution = (microtime(true) + $delay);
            $this->alarmOrder[$watcherId] = $nextExecution;
        } else {
            $nextExecution = NULL;
        }

        $alarmStruct = [$callback, $nextExecution, $delay, $isRepeating];
        $this->alarms[$watcherId] = $alarmStruct;

        return $watcherId;
    }

    public function onReadable($stream, callable $callback, $enableNow = true) {
        $watcherId = $this->lastWatcherId++;

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

    public function onWritable($stream, callable $callback, $enableNow = true) {
        $watcherId = $this->lastWatcherId++;

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

    public function watchStream($stream, $flags, callable $callback) {
        $flags = (int) $flags;
        $enableNow = ($flags & self::WATCH_NOW);

        if ($flags & self::WATCH_READ) {
            return $this->onWritable($stream, $callback, $enableNow);
        } elseif ($flags & self::WATCH_WRITE) {
            return $this->onWritable($stream, $callback, $enableNow);
        } else {
            throw new \DomainException(
                'Stream watchers must specify either a WATCH_READ or WATCH_WRITE flag'
            );
        }
    }

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
        }
    }

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
        }
    }

}
