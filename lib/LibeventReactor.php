<?php

namespace Alert;

class LibeventReactor implements SignalReactor {

    private $base;
    private $watchers = [];
    private $lastWatcherId = 0;
    private $resolution = 1000;
    private $isRunning = false;
    private $isGCScheduled = false;
    private $garbage = [];
    private $gcEvent;
    private $stopException;

    public function __construct() {
        $this->base = event_base_new();
        $this->gcEvent = event_new();
        event_timer_set($this->gcEvent, [$this, 'collectGarbage']);
        event_base_set($this->gcEvent, $this->base);
    }

    public function run(callable $onStart = null) {
        if ($this->isRunning) {
            return;
        }

        if ($onStart) {
            $this->immediately(function() use ($onStart) { $onStart($this); });
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
            $this->stopException = null;
            throw $e;
        }
    }

    public function stop() {
        event_base_loopexit($this->base);
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
        return $this->once($callback, $msDelay = 0);
    }

    public function once(callable $callback, $msDelay) {
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapOnceCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $msDelay);

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

    public function repeat(callable $callback, $msDelay) {
        $watcherId = $this->lastWatcherId++;
        $msDelay = ($msDelay > 0) ? ($msDelay * $this->resolution) : 0;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->msDelay = $msDelay;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapRepeatingCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_timer_set($eventResource, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource, $msDelay);

        return $watcherId;
    }

    private function wrapRepeatingCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;
        $eventResource = $watcher->eventResource;
        $msDelay = $watcher->msDelay;

        return function() use ($callback, $eventResource, $msDelay, $watcherId) {
            try {
                $callback($watcherId, $this);
                event_add($eventResource, $msDelay);
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
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();

        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->stream = $stream;
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

    public function onSignal($signal, callable $callback) {
        $signal = (int) $signal;
        $watcherId = $this->lastWatcherId++;
        $eventResource = event_new();
        $watcher = new LibeventWatcher;
        $watcher->id = $watcherId;
        $watcher->eventResource = $eventResource;
        $watcher->callback = $callback;

        $watcher->wrapper = $this->wrapSignalCallback($watcher);

        $this->watchers[$watcherId] = $watcher;

        event_set($eventResource, $signal, EV_SIGNAL | EV_PERSIST, $watcher->wrapper);
        event_base_set($eventResource, $this->base);
        event_add($eventResource);

        return $watcherId;
    }

    private function wrapSignalCallback(LibeventWatcher $watcher) {
        $callback = $watcher->callback;
        $watcherId = $watcher->id;

        return function() use ($callback, $watcherId) {
            try {
                $callback($watcherId, $this);
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
            event_add($watcher->eventResource, $watcher->msDelay);
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
