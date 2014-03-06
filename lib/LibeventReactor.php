<?php

namespace Alert;

class LibeventReactor implements Reactor {

    private $base;
    private $watchers = [];
    private $lastWatcherId;
    private $resolution = 1000000;
    private $isRunning = false;
    private $isGCScheduled = false;
    private $gcEvent;
    private $stopException;

    private static $TYPE_STREAM = 0;
    private static $TYPE_ONCE = 1;
    private static $TYPE_REPEATING = 2;

    public function __construct() {
        $this->lastWatcherId = PHP_INT_MAX * -1;
        $this->base = event_base_new();
        $this->gcEvent = event_new();
        event_timer_set($this->gcEvent, [$this, 'collectGarbage']);
        event_base_set($this->gcEvent, $this->base);
    }

    public function run(callable $onStart = NULL) {
        if ($this->isRunning) {
            return;
        }

        if ($onStart) {
            $this->immediately($onStart);
        }

        $this->doRun();
    }

    public function tick() {
        if (!$this->isRunning) {
            $this->doRun(EVLOOP_ONCE | EVLOOP_NONBLOCK);
        }
    }

    private function doRun($flags = 0) {
        $this->isRunning = true;
        event_base_loop($this->base, $flags);
        $this->isRunning = false;

        if ($this->stopException) {
            $e = $this->stopException;
            throw $e;
        }
    }

    public function stop() {
        event_base_loopexit($this->base);
    }

    public function at(callable $callback, $timeString) {
        $now = time();
        $executeAt = @strtotime($timeString);

        if ($executeAt === false && $executeAt <= $now) {
            throw new \InvalidArgumentException(
                'Valid future time string (parsable by strtotime()) required'
            );
        }

        $delay = $executeAt - $now;

        return $this->once($callback, $delay);
    }

    public function immediately(callable $callback) {
        return $this->once($callback, $delay = 0);
    }

    public function once(callable $callback, $delay) {
        $watcherId = ++$this->lastWatcherId;
        $eventResource = event_new();
        $delay = ($delay > 0) ? ($delay * $this->resolution) : 0;

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->type = self::$TYPE_ONCE;
        $watcher->eventResource = $eventResource;
        $watcher->interval = $delay;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapOnceCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $delay);

        return $watcherId;
    }

    private function wrapOnceCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;

        return function() use ($callback, $watcherId) {
            try {
                $callback($watcherId, $this);
                $this->cancel($watcherId);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    public function repeat(callable $callback, $interval) {
        $watcherId = ++$this->lastWatcherId;
        $interval = ($interval > 0) ? ($interval * $this->resolution) : 0;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->type = self::$TYPE_REPEATING;
        $watcher->eventResource = $eventResource;
        $watcher->interval = $interval;
        $watcher->callback = $callback;
        $watcher->isRepeating = true;

        $watcher->wrapper = $this->wrapRepeatingCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $interval);

        return $watcherId;
    }

    private function wrapRepeatingCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $eventResource = $watcher->eventResource;
        $interval = $watcher->interval;

        return function() use ($callback, $eventResource, $interval, $watcherId) {
            try {
                $callback($watcherId, $this);
                event_add($eventResource, $interval);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    public function onReadable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, EV_READ | EV_PERSIST, $callback, $enableNow);
    }

    public function onWritable($stream, callable $callback, $enableNow = true) {
        return $this->watchIoStream($stream, EV_WRITE | EV_PERSIST, $callback, $enableNow);
    }

    private function watchIoStream($stream, $flags, callable $callback, $enableNow) {
        $watcherId = ++$this->lastWatcherId;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->type = self::$TYPE_STREAM;
        $watcher->stream = $stream;
        $watcher->streamFlags = $flags;
        $watcher->callback = $callback;
        $watcher->wrapper = $this->wrapStreamCallback($watcher);
        $watcher->isEnabled = (bool) $enableNow;
        $watcher->eventResource = $eventResource;

        $this->watchers[$watcherId] = $watcher;

        event_set($eventResource, $stream, $flags, $watcher->wrapper);
        event_base_set($eventResource, $this->base);

        if ($enableNow) {
            event_add($eventResource);
        }

        return $watcherId;
    }

    private function wrapStreamCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;

        return function($stream) use ($callback, $watcherId) {
            try {
                $callback($watcherId, $stream, $this);
            } catch (\Exception $e) {
                $this->stopException = $e;
                $this->stop();
            }
        };
    }

    public function cancel($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        event_del($watcher->eventResource);
        $this->garbage[] = $watcher;
        $this->scheduleGarbageCollection();
        unset($this->watchers[$watcherId]);
    }

    public function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            event_del($watcher->eventResource);
            $watcher->isEnabled = false;
        }
    }

    public function enable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if (!$watcher->isEnabled) {
            event_add($watcher->eventResource, $watcher->interval);
            $watcher->isEnabled = true;
        }
    }

    private function scheduleGarbageCollection() {
        if (!$this->isGCScheduled) {
            event_add($this->gcEvent, 0);
            $this->isGCScheduled = true;
        }
    }

    private function collectGarbage() {
        $this->garbage = [];
        $this->isGCScheduled = false;
        event_del($this->gcEvent);
    }

}
