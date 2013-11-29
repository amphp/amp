<?php

namespace Alert;

class LibeventReactor implements Reactor, Forkable {

    private $base;
    private $watchers = [];
    private $lastWatcherId;
    private $resolution = 1000000;
    private $isRunning = FALSE;
    private $isGCScheduled = FALSE;
    private $gcEvent;
    private $stopException;

    private static $TYPE_STREAM = 0;
    private static $TYPE_ONCE = 1;
    private static $TYPE_REPEATING = 2;

    function __construct() {
        $this->lastWatcherId = PHP_INT_MAX * -1;
        $this->initialize();
    }

    /**
     * Normally this would go into the __construct() function but it's split out into its own
     * method because we also have to initialize() when calling afterFork().
     */
    private function initialize() {
        $this->base = event_base_new();
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

    function repeat(callable $callback, $interval) {
        $watcherId = ++$this->lastWatcherId;
        $interval = ($interval > 0) ? ($interval * $this->resolution) : 0;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->type = self::$TYPE_REPEATING;
        $watcher->eventResource = $eventResource;
        $watcher->interval = $interval;
        $watcher->callback = $callback;
        $watcher->isRepeating = TRUE;

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

    function onReadable($stream, callable $callback, $enableNow = TRUE) {
        return $this->watchIoStream($stream, EV_READ | EV_PERSIST, $callback, $enableNow);
    }

    function onWritable($stream, callable $callback, $enableNow = TRUE) {
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

    function cancel($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        event_del($watcher->eventResource);
        $this->garbage[] = $watcher;
        $this->scheduleGarbageCollection();
        unset($this->watchers[$watcherId]);
    }

    function disable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];
        if ($watcher->isEnabled) {
            event_del($watcher->eventResource);
            $watcher->isEnabled = FALSE;
        }
    }

    function enable($watcherId) {
        if (!isset($this->watchers[$watcherId])) {
            return;
        }

        $watcher = $this->watchers[$watcherId];

        if (!$watcher->isEnabled) {
            event_add($watcher->eventResource, $watcher->interval);
            $watcher->isEnabled = TRUE;
        }
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
        $this->initialize();

        foreach ($this->watchers as $watcherId => $watcher) {
            $eventResource = event_new();
            $watcher->eventResource = $eventResource;

            switch ($watcher->type) {
                case self::$TYPE_STREAM:
                    $wrapper = $this->wrapStreamCallback($watcher);
                    event_set($eventResource, $watcher->stream, $watcher->streamFlags, $wrapper);
                    break;

                case self::$TYPE_ONCE:
                    $wrapper = $this->wrapOnceCallback($watcher);
                    event_timer_set($eventResource, $wrapper);
                    break;

                case self::$TYPE_REPEATING:
                    $wrapper = $this->wrapRepeatingCallback($watcher);
                    event_timer_set($eventResource, $wrapper);
                    break;
            }

            $watcher->wrapper = $wrapper;
            event_base_set($eventResource, $this->base);

            if ($watcher->isEnabled) {
                event_add($eventResource, $watcher->interval);
            }
        }
    }

}
