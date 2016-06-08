<?php

namespace Amp\Loop;

use Amp\Loop\Internal\Watcher;
use Interop\Async\Loop\Driver;
use Interop\Async\Loop\InvalidWatcherException;
use Interop\Async\Loop\Registry;
use Interop\Async\Loop\UnsupportedFeatureException;

class NativeLoop implements Driver {
    use Registry;

    const MILLISEC_PER_SEC = 1e3;
    const MICROSEC_PER_SEC = 1e6;
    
    /**
     * @var string
     */
    private $nextId = "a";

    /**
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $watchers = [];

    /**
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $enableQueue = [];

    /**
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $deferQueue = [];

    /**
     * @var resource[]
     */
    private $readStreams = [];

    /**
     * @var \Amp\Loop\Internal\Watcher[][]
     */
    private $readWatchers = [];

    /**
     * @var resource[]
     */
    private $writeStreams = [];

    /**
     * @var \Amp\Loop\Internal\Watcher[][]
     */
    private $writeWatchers = [];

    /**
     * @var int[]
     */
    private $timerExpires = [];

    /**
     * @var \SplPriorityQueue
     */
    private $timerQueue;

    /**
     * @var \Amp\Loop\Internal\Watcher[][]
     */
    private $signalWatchers = [];

    /**
     * @var callable
     */
    private $errorHandler;

    /**
     * @var int
     */
    private $running = 0;

    /**
     * @var bool
     */
    private $signalHandling;

    public function __construct() {
        $this->timerQueue = new \SplPriorityQueue();
        $this->signalHandling = \extension_loaded("pcntl");
    }
    
    /**
     * {@inheritdoc}
     */
    public function run() {
        $previous = $this->running++;

        try {
            while ($this->running > $previous) {
                if ($this->isEmpty()) {
                    return;
                }
                $this->tick();
            }
        } finally {
            $this->running = $previous;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop() {
        --$this->running;
    }

    /**
     * @return bool True if no enabled and referenced watchers remain in the loop.
     */
    private function isEmpty() {
        foreach ($this->watchers as $watcher) {
            if ($watcher->enabled && $watcher->referenced) {
                return false;
            }
        }

        return true;
    }

    /**
     * Executes a single tick of the event loop.
     */
    private function tick() {
        try {
            if (!empty($this->deferQueue)) {
                $this->invokeDeferred();
            }

            $this->selectStreams(
                $this->readStreams,
                $this->writeStreams,
                empty($this->enableQueue) ? $this->getTimeout() : 0
            );

            if (!empty($this->timerExpires)) {
                $this->invokeTimers();
            }

            if ($this->signalHandling) {
                \pcntl_signal_dispatch();
            }

            if (!empty($this->enableQueue)) {
                $this->enableWatchers();
            }
        } catch (\Throwable $exception) {
            if (null === $this->errorHandler) {
                throw $exception;
            }

            $errorHandler = $this->errorHandler;
            $errorHandler($exception);
        } catch (\Exception $exception) { // @todo Remove when PHP 5.x support is no longer needed.
            if (null === $this->errorHandler) {
                throw $exception;
            }

            $errorHandler = $this->errorHandler;
            $errorHandler($exception);
        }
    }

    /**
     * @param resource[] $read
     * @param resource[] $write
     * @param int $timeout
     */
    private function selectStreams(array $read, array $write, $timeout) {
        $timeout /= self::MILLISEC_PER_SEC;

        if (!empty($read) || !empty($write)) { // Use stream_select() if there are any streams in the loop.
            if ($timeout >= 0) {
                $seconds = (int) $timeout;
                $microseconds = (int) (($timeout - $seconds) * self::MICROSEC_PER_SEC);
            } else {
                $seconds = null;
                $microseconds = null;
            }

            $except = null;

            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal.
            $count = @\stream_select($read, $write, $except, $seconds, $microseconds);

            if ($count) {
                foreach ($read as $stream) {
                    $streamId = (int) $stream;
                    if (isset($this->readWatchers[$streamId])) {
                        foreach ($this->readWatchers[$streamId] as $watcher) {
                            if (!isset($this->readWatchers[$streamId][$watcher->id])) {
                                continue; // Watcher disabled by another IO watcher.
                            }

                            $callback = $watcher->callback;
                            $callback($watcher->id, $stream, $watcher->data);
                        }
                    }
                }

                foreach ($write as $stream) {
                    $streamId = (int) $stream;
                    if (isset($this->writeWatchers[$streamId])) {
                        foreach ($this->writeWatchers[$streamId] as $watcher) {
                            if (!isset($this->writeWatchers[$streamId][$watcher->id])) {
                                continue; // Watcher disabled by another IO watcher.
                            }

                            $callback = $watcher->callback;
                            $callback($watcher->id, $stream, $watcher->data);
                        }
                    }
                }
            }

            return;
        }

        if ($timeout > 0) { // Otherwise sleep with usleep() if $timeout > 0.
            \usleep($timeout * self::MICROSEC_PER_SEC);
        }
    }

    /**
     * @return int Milliseconds until next timer expires or -1 if there are no pending times.
     */
    private function getTimeout() {
        while (!$this->timerQueue->isEmpty()) {
            list($watcher, $expiration) = $this->timerQueue->top();

            $id = $watcher->id;

            if (!isset($this->timerExpires[$id]) || $expiration !== $this->timerExpires[$id]) {
                $this->timerQueue->extract(); // Timer was removed from queue.
                continue;
            }

            $expiration -= (int) (\microtime(true) * self::MILLISEC_PER_SEC);

            if ($expiration < 0) {
                return 0;
            }

            return $expiration;
        }

        return -1;
    }

    /**
     * Invokes all pending defer watchers.
     */
    private function invokeDeferred() {
        foreach ($this->deferQueue as $watcher) {
            $id = $watcher->id;

            if (!isset($this->deferQueue[$id])) {
                continue; // Watcher disabled by another defer watcher.
            }

            unset($this->watchers[$id], $this->deferQueue[$id]);

            $callback = $watcher->callback;
            $callback($id, $watcher->data);
        }
    }

    /**
     * Invokes all pending delay and repeat watchers.
     */
    private function invokeTimers() {
        $time = (int) (\microtime(true) * self::MILLISEC_PER_SEC);
    
        while (!$this->timerQueue->isEmpty()) {
            list($watcher, $expiration) = $this->timerQueue->top();

            $id = $watcher->id;

            if (!isset($this->timerExpires[$id]) || $expiration !== $this->timerExpires[$id]) {
                $this->timerQueue->extract(); // Timer was removed from queue.
                continue;
            }
        
            if ($this->timerExpires[$id] > $time) { // Timer at top of queue has not expired.
                return;
            }
        
            $this->timerQueue->extract();

            unset($this->timerExpires[$id]);
        
            if ($watcher->type & Watcher::REPEAT) {
                $this->enableQueue[$id] = $watcher;
            } else {
                unset($this->watchers[$id]);
            }
        
            // Execute the timer.
            $callback = $watcher->callback;
            $callback($id, $watcher->data);
        }
    }

    /**
     * Enables any watchers queued to be enabled on the next tick.
     */
    private function enableWatchers() {
        $enableQueue = $this->enableQueue;
        $this->enableQueue = [];

        foreach ($enableQueue as $watcher) {
            switch ($watcher->type) {
                case Watcher::READABLE:
                    $streamId = (int) $watcher->value;
                    $this->readWatchers[$streamId][$watcher->id] = $watcher;
                    $this->readStreams[$streamId] = $watcher->value;
                    break;
        
                case Watcher::WRITABLE:
                    $streamId = (int) $watcher->value;
                    $this->writeWatchers[$streamId][$watcher->id] = $watcher;
                    $this->writeStreams[$streamId] = $watcher->value;
                    break;
        
                case Watcher::DELAY:
                case Watcher::REPEAT:
                    $priority = (\microtime(true) * self::MILLISEC_PER_SEC) + $watcher->value;
                    $expiration = (int) $priority;
                    $this->timerExpires[$watcher->id] = $expiration;
                    $this->timerQueue->insert([$watcher, $expiration], -$priority);
                    break;
        
                case Watcher::DEFER:
                    $this->deferQueue[$watcher->id] = $watcher;
                    break;
        
                case Watcher::SIGNAL:
                    if (!isset($this->signalWatchers[$watcher->value])) {
                        if (!@\pcntl_signal($watcher->value, function ($signo) {
                            foreach ($this->signalWatchers[$signo] as $watcher) {
                                if (!isset($this->signalWatchers[$signo][$watcher->id])) {
                                    continue;
                                }

                                $callback = $watcher->callback;
                                $callback($watcher->id, $signo, $watcher->data);
                            }
                        })) {
                            throw new \RuntimeException("Failed to register signal handler");
                        }
                    }

                    $this->signalWatchers[$watcher->value][$watcher->id] = $watcher;
                    break;
        
                default: throw new \DomainException("Unknown watcher type");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defer(callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::DEFER;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->data = $data;
        
        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function delay($delay, callable $callback, $data = null) {
        $delay = (int) $delay;

        if ($delay < 0) {
            throw new \InvalidArgumentException("Delay must be greater than or equal to zero");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::DELAY;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->value = $delay;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function repeat($interval, callable $callback, $data = null) {
        $interval = (int) $interval;

        if ($interval < 0) {
            throw new \InvalidArgumentException("Interval must be greater than or equal to zero");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::REPEAT;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->value = $interval;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onReadable($stream, callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::READABLE;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->value = $stream;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;
    
        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onWritable($stream, callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::WRITABLE;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->value = $stream;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Interop\Async\Loop\UnsupportedFeatureException If the pcntl extension is not available.
     * @throws \RuntimeException If creating the backend signal handler fails.
     */
    public function onSignal($signo, callable $callback, $data = null) {
        if (!$this->signalHandling) {
            throw new UnsupportedFeatureException("Signal handling requires the pcntl extension");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::SIGNAL;
        $watcher->id = $this->nextId++;
        $watcher->callback = $callback;
        $watcher->value = $signo;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $this->enableQueue[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setErrorHandler(callable $callback = null) {
        $this->errorHandler = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function enable($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            throw new InvalidWatcherException("Cannot enable an invalid watcher identifier");
        }

        $watcher = $this->watchers[$watcherIdentifier];

        if ($watcher->enabled) {
            return; // Watcher already enabled.
        }

        $watcher->enabled = true;
        $this->enableQueue[$watcher->id] = $watcher;
    }

    /**
     * {@inheritdoc}
     */
    public function disable($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            throw new InvalidWatcherException("Cannot disable an invalid watcher identifier");
        }

        $watcher = $this->watchers[$watcherIdentifier];

        if (!$watcher->enabled) {
            return; // Watcher already disabled.
        }

        $watcher->enabled = false;
        $id = $watcher->id;

        if (isset($this->enableQueue[$id])) {
            unset($this->enableQueue[$id]);
            return; // Watcher was only queued to be enabled.
        }

        switch ($watcher->type) {
            case Watcher::READABLE:
                $streamId = (int) $watcher->value;
                unset($this->readWatchers[$streamId][$id]);
                if (empty($this->readWatchers[$streamId])) {
                    unset($this->readWatchers[$streamId], $this->readStreams[$streamId]);
                }
                break;

            case Watcher::WRITABLE:
                $streamId = (int) $watcher->value;
                unset($this->writeWatchers[$streamId][$id]);
                if (empty($this->writeWatchers[$streamId])) {
                    unset($this->writeWatchers[$streamId], $this->writeStreams[$streamId]);
                }
                break;

            case Watcher::DELAY:
            case Watcher::REPEAT:
                unset($this->timerExpires[$id]);
                break;

            case Watcher::DEFER:
                unset($this->deferQueue[$id]);
                break;

            case Watcher::SIGNAL:
                if (isset($this->signalWatchers[$watcher->value])) {
                    unset($this->signalWatchers[$watcher->value][$id]);

                    if (empty($this->signalWatchers[$watcher->value])) {
                        unset($this->signalWatchers[$watcher->value]);
                        @\pcntl_signal($watcher->value, \SIG_DFL);
                    }
                }
                break;

            default: throw new \DomainException("Unknown watcher type");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            return; // Avoid throwing from disable() if the watcher is invalid.
        }

        $this->disable($watcherIdentifier);
        unset($this->watchers[$watcherIdentifier]);
    }

    /**
     * {@inheritdoc}
     */
    public function reference($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            throw new InvalidWatcherException("Cannot reference an invalid watcher identifier");
        }

        $this->watchers[$watcherIdentifier]->referenced = true;
    }

    /**
     * {@inheritdoc}
     */
    public function unreference($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            throw new InvalidWatcherException("Cannot unreference an invalid watcher identifier");
        }

        $this->watchers[$watcherIdentifier]->referenced = false;
    }

    /**
     * {@inheritdoc}
     */
    public function info() {
        $watchers = [
            "referenced"   => 0,
            "unreferenced" => 0,
        ];

        $defer = $delay = $repeat = $onReadable = $onWritable = $onSignal = [
            "enabled"  => 0,
            "disabled" => 0,
        ];

        foreach ($this->watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::READABLE: $array = &$onReadable; break;
                case Watcher::WRITABLE: $array = &$onWritable; break;
                case Watcher::SIGNAL:   $array = &$onSignal; break;
                case Watcher::DEFER:    $array = &$defer; break;
                case Watcher::DELAY:    $array = &$delay; break;
                case Watcher::REPEAT:   $array = &$repeat; break;

                default: throw new \DomainException("Unknown watcher type");
            }

            if ($watcher->enabled) {
                ++$array["enabled"];

                if ($watcher->referenced) {
                    ++$watchers["referenced"];
                } else {
                    ++$watchers["unreferenced"];
                }
            } else {
                ++$array["disabled"];
            }
        }

        return [
            "watchers"    => $watchers,
            "defer"       => $defer,
            "delay"       => $delay,
            "repeat"      => $repeat,
            "on_readable" => $onReadable,
            "on_writable" => $onWritable,
            "on_signal"   => $onSignal,
            "running"     => $this->running,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return null;
    }

    /**
     * Returns the same array of data as info().
     *
     * @return array
     */
    public function __debugInfo() {
        return $this->info();
    }
}
