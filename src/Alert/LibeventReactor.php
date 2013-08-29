<?php

namespace Alert;

class LibeventReactor implements Reactor, Forkable {

    private $base;
    private $watchers = [];
    private $lastWatcherId = 0;
    private $resolution = 1000000;
    private $isRunning = FALSE;
    private $isGCScheduled = FALSE;
    private $gcEvent;
    private $stopException;

    function __construct() {
        $this->base = event_base_new();
        $this->initializeGc();
    }

    private function initializeGc() {
        $this->gcEvent = event_new();
        event_timer_set($this->gcEvent, [$this, 'collectGarbage']);
        event_base_set($this->gcEvent, $this->base);
    }

    function run() {
        if (!$this->isRunning) {
            $this->doRun();
        }
    }

    function tick() {
        if (!$this->isRunning) {
            $this->doRun(EVLOOP_ONCE | EVLOOP_NONBLOCK);
        }
    }

    private function doRun($flags = 0) {
        $this->isRunning = TRUE;
        event_base_loop($this->base, $flags);
        $this->isRunning = FALSE;

        if ($this->stopException) {
            $e = $this->stopException;
            throw $e;
        }
    }

    function stop() {
        event_base_loopexit($this->base);
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
        return $this->once($callback, $delay = 0);
    }

    function once(callable $callback, $delay) {
        $watcherId = $this->getNextWatcherId();
        $event = event_new();
        $delay = ($delay > 0) ? ($delay * $this->resolution) : 0;

        $wrapper = function() use ($callback, $watcherId) {
            try {
                $callback($watcherId);
                $this->cancel($watcherId);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };

        $this->watchers[$watcherId] = [$event, $isEnabled = TRUE, $wrapper, $delay];

        event_timer_set($event, $wrapper);
        event_base_set($event, $this->base);
        event_add($event, $delay);

        return $watcherId;
    }

    function repeat(callable $callback, $interval) {
        $watcherId = $this->getNextWatcherId();
        $event = event_new();
        $interval = ($interval > 0) ? ($interval * $this->resolution) : 0;

        $wrapper = function() use ($callback, $event, $interval, $watcherId) {
            try {
                $callback($watcherId);
                event_add($event, $interval);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };

        $this->watchers[$watcherId] = [$event, $isEnabled = TRUE, $wrapper, $interval];

        event_timer_set($event, $wrapper);
        event_base_set($event, $this->base);
        event_add($event, $interval);

        return $watcherId;
    }

    function onReadable($stream, callable $callback, $enableNow = TRUE) {
        return $this->watchIoStream($stream, EV_READ | EV_PERSIST, $callback, $enableNow);
    }

    function onWritable($stream, callable $callback, $enableNow = TRUE) {
        return $this->watchIoStream($stream, EV_WRITE | EV_PERSIST, $callback, $enableNow);
    }

    private function watchIoStream($stream, $flags, callable $callback, $enableNow) {
        $watcherId = $this->getNextWatcherId();
        $event = event_new();

        $wrapper = function($stream) use ($callback, $watcherId) {
            try {
                $callback($watcherId, $stream);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };

        event_set($event, $stream, $flags, $wrapper);
        event_base_set($event, $this->base);

        if ($enableNow) {
            event_add($event);
        }

        $this->watchers[$watcherId] = [$event, $enableNow, $wrapper, $interval = -1];

        return $watcherId;
    }

    function cancel($watcherId) {
        if (isset($this->watchers[$watcherId])) {
            $watcherStruct = $this->watchers[$watcherId];
            event_del($watcherStruct[0]);
            $this->garbage[] = $watcherStruct;
            $this->scheduleGarbageCollection();
            unset($this->watchers[$watcherId]);
        }
    }

    function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        list($event, $isEnabled, $wrapper, $interval) = $this->watchers[$watcherId];

        if ($isEnabled) {
            event_del($event);
            $isEnabled = FALSE;
            $this->watchers[$watcherId] = [$event, $isEnabled, $wrapper, $interval];
        }
    }

    function enable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        list($event, $isEnabled, $wrapper, $interval) = $this->watchers[$watcherId];

        if (!$isEnabled) {
            event_add($event, $interval);
            $isEnabled = TRUE;
            $this->watchers[$watcherId] = [$event, $isEnabled, $wrapper, $interval];
        }
    }

    private function getNextWatcherId() {
        if (($watcherId = ++$this->lastWatcherId) === PHP_INT_MAX) {
            $this->lastWatcherId = 0;
        }

        return $watcherId;
    }

    private function scheduleGarbageCollection() {
        if (!$this->isGCScheduled) {
            event_add($this->gcEvent, 0);
            $this->isGCScheduled = TRUE;
        }
    }

    private function collectGarbage() {
        $this->garbage = [];
        $this->isGCScheduled = FALSE;
        event_del($this->gcEvent);
    }

    function beforeFork() {
        $this->collectGarbage();
    }

    function afterFork() {
        $this->base = event_base_new();
        $this->initializeGc();

        foreach ($this->watchers as $watcherId => $watcherStruct) {
            list($event, $enableNow) = $watcherStruct;
            event_base_set($event, $this->base);

            if ($enableNow) {
                event_add($event);
            }

            $this->watchers[$watcherId][0] = $event;
        }
    }
}

