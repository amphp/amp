<?php

namespace Amp\Loop;

use Amp\Loop\Internal\Watcher;
use Interop\Async\LoopDriver;
use Interop\Async\UnsupportedFeatureException;

class NativeLoop implements LoopDriver {
    const MILLISEC_PER_SEC = 1e3;
    const MICROSEC_PER_SEC = 1e6;

    /**
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $watchers = [];

    /**
     * @var string[]
     */
    private $deferQueue = [];

    /**
     * @var resource[]
     */
    private $readStreams = [];

    /**
     * @var string[]
     */
    private $readWatchers = [];

    /**
     * @var resource[]
     */
    private $writeStreams = [];

    /**
     * @var string[]
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
     * @var string[][]
     */
    private $signalWatchers = [];

    /**
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $unreferenced = [];

    /**
     * @var callable
     */
    private $errorHandler;

    /**
     * @var bool
     */
    private $running = false;

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
     * 
     * @throws \Amp\Loop\Exception\AlreadyRunningException
     */
    public function run() {
        if ($this->running) {
            throw new Exception\AlreadyRunningException("Cannot run loop recursively; loop already running");
        }

        $this->running = true;

        try {
            while ($this->running) {
                if ($this->isEmpty()) {
                    return;
                }
                $this->tick();
            }
        } finally {
            $this->stop();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop() {
        $this->running = false;
    }

    /**
     * @return bool True if no referenced watchers remain in the loop.
     */
    private function isEmpty() {
        if (empty($this->watchers)) {
            return true;
        }

        if (empty($this->unreferenced)) {
            return false;
        }

        return \count($this->watchers) === \count($this->unreferenced);
    }

    /**
     * Executes a single tick of the event loop.
     */
    private function tick() {
        try {
            if (!empty($this->deferQueue)) {
                $this->invokeDeferred();
            }

            $this->selectStreams($this->readStreams, $this->writeStreams, $this->getTimeout());

            if (!empty($this->timerExpires)) {
                $this->invokeTimers();
            }

            if ($this->signalHandling) {
                \pcntl_signal_dispatch();
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
                    $key = (int) $stream;
                    if (isset($this->readStreams[$key], $this->readWatchers[$key])) {
                        $watcher = $this->watchers[$this->readWatchers[$key]];

                        $callback = $watcher->callback;
                        $callback($watcher->id, $stream, $watcher->data);
                    }
                }

                foreach ($write as $stream) {
                    $key = (int) $stream;
                    if (isset($this->writeStreams[$key], $this->writeWatchers[$key])) {
                        $watcher = $this->watchers[$this->writeWatchers[$key]];

                        $callback = $watcher->callback;
                        $callback($watcher->id, $stream, $watcher->data);
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
            list($id, $timeout) = $this->timerQueue->top();

            if (!isset($this->timerExpires[$id]) || $timeout !== $this->timerExpires[$id]) {
                $this->timerQueue->extract(); // Timer was removed from queue.
                continue;
            }

            $timeout -= (int) (\microtime(true) * self::MILLISEC_PER_SEC);

            if ($timeout < 0) {
                return 0;
            }

            return $timeout;
        }

        return -1;
    }

    /**
     * Invokes all pending defer watchers.
     */
    private function invokeDeferred() {
        $queue = $this->deferQueue;
        $this->deferQueue = [];
        $count = 0;

        try {
            foreach ($queue as $id) {
                ++$count;

                if (!isset($this->watchers[$id])) {
                    continue;
                }

                $watcher = $this->watchers[$id];
                unset($this->watchers[$id], $this->unreferenced[$id]);

                $callback = $watcher->callback;
                $callback($watcher->id, $watcher->data);
            }
        } catch (\Throwable $exception) {
            if ($count !== \count($queue)) {
                $this->deferQueue = \array_merge(\array_slice($queue, $count), $this->deferQueue);
            }
            throw $exception;
        } catch (\Exception $exception) {
            if ($count !== \count($queue)) {
                $this->deferQueue = \array_merge(\array_slice($queue, $count), $this->deferQueue);
            }
            throw $exception;
        }
    }

    /**
     * Invokes all pending delay and repeat watchers.
     */
    private function invokeTimers() {
        $time = (int) (\microtime(true) * self::MILLISEC_PER_SEC);
    
        while (!$this->timerQueue->isEmpty()) {
            list($id, $timeout) = $this->timerQueue->top();
    
            if (!isset($this->timerExpires[$id]) || $timeout !== $this->timerExpires[$id]) {
                $this->timerQueue->extract(); // Timer was removed from queue.
                continue;
            }
        
            if ($this->timerExpires[$id] > $time) { // Timer at top of queue has not expired.
                return;
            }
        
            // Remove and execute timer. Replace timer if persistent.
            $this->timerQueue->extract();
        
            $watcher = $this->watchers[$id];
            
            if ($watcher->type === Watcher::REPEAT) {
                $timeout = $time + $watcher->value;
                $this->timerQueue->insert([$id, $timeout], -$timeout);
                $this->timerExpires[$id] = $timeout;
            } else {
                unset($this->watchers[$id], $this->timerExpires[$id], $this->unreferenced[$id]);
            }
        
            // Execute the timer.
            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defer(callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::DEFER;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->data = $data;
        
        $this->watchers[$watcher->id] = $watcher;
        $this->deferQueue[] = $watcher->id;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function delay($delay, callable $callback, $data = null) {
        $delay = (int) $delay;

        if ($delay <= 0) {
            throw new \InvalidArgumentException("Delay must be greater than or equal to zero");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::DELAY;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->value = $delay;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;

        $expiration = (int) (\microtime(true) * self::MILLISEC_PER_SEC) + $watcher->value;

        $this->timerExpires[$watcher->id] = $expiration;
        $this->timerQueue->insert([$watcher->id, $expiration], -$expiration);

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function repeat($interval, callable $callback, $data = null) {
        $interval = (int) $interval;

        if ($interval <= 0) {
            throw new \InvalidArgumentException("Interval must be greater than or equal to zero");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::REPEAT;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->value = $interval;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;

        $expiration = (int) (\microtime(true) * self::MILLISEC_PER_SEC) + $watcher->value;

        $this->timerExpires[$watcher->id] = $expiration;
        $this->timerQueue->insert([$watcher->id, $expiration], -$expiration);

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onReadable($stream, callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::READABLE;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->value = $stream;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $key = (int) $watcher->value;
        $this->readWatchers[$key] = $watcher->id;
        $this->readStreams[$key] = $watcher->value;
    
        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onWritable($stream, callable $callback, $data = null) {
        $watcher = new Watcher;
        $watcher->type = Watcher::WRITABLE;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->value = $stream;
        $watcher->data = $data;

        $this->watchers[$watcher->id] = $watcher;
        $key = (int) $watcher->value;
        $this->writeWatchers[$key] = $watcher->id;
        $this->writeStreams[$key] = $watcher->value;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Interop\Async\UnsupportedFeatureException If the pcntl extension is not available.
     * @throws \Amp\Loop\Exception\SignalHandlerException If creating the backend signal handler fails.
     */
    public function onSignal($signo, callable $callback, $data = null) {
        if (!$this->signalHandling) {
            throw new UnsupportedFeatureException("Signal handling requires the pcntl extension");
        }

        $watcher = new Watcher;
        $watcher->type = Watcher::WRITABLE;
        $watcher->id = \spl_object_hash($watcher);
        $watcher->callback = $callback;
        $watcher->value = $signo;
        $watcher->data = $data;

        $this->enableSignal($watcher);
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * @param \Amp\Loop\Internal\Watcher $watcher
     *
     * @throws \Amp\Loop\Exception\SignalHandlerException If creating the backend signal handler fails.
     */
    private function enableSignal(Watcher $watcher) {
        if (!isset($this->signalWatchers[$watcher->value])) {
            if (!@\pcntl_signal($watcher->value, function ($signo) {
                foreach ($this->signalWatchers[$signo] as $id) {
                    if (!isset($this->watchers[$id])) {
                        continue;
                    }

                    $watcher = $this->watchers[$id];

                    $callback = $watcher->callback;
                    $callback($watcher->id, $watcher->value, $watcher->data);
                }
            })) {
                throw new Exception\SignalHandlerException("Failed to register signal handler");
            }

            $this->signalWatchers[$watcher->value] = [];
        }

        $this->signalWatchers[$watcher->value][$watcher->id] = $watcher->id;
    }

    /**
     * @param \Amp\Loop\Internal\Watcher $watcher
     */
    private function disableSignal(Watcher $watcher) {
        if (isset($this->signalWatchers[$watcher->value])) {
            unset($this->signalWatchers[$watcher->value][$watcher->id]);

            if (empty($this->signalWatchers[$watcher->value])) {
                unset($this->signalWatchers[$watcher->value]);
                @\pcntl_signal($watcher->value, \SIG_DFL);
            }
        }
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
            return;
        }

        $watcher = $this->watchers[$watcherIdentifier];

        switch ($watcher->type) {
            case Watcher::READABLE:
                $key = (int) $watcher->value;
                $this->readWatchers[$key] = $watcher->id;
                $this->readStreams[$key] = $watcher->value;
                break;

            case Watcher::WRITABLE:
                $key = (int) $watcher->value;
                $this->writeWatchers[$key] = $watcher->id;
                $this->writeStreams[$key] = $watcher->value;
                break;

            case Watcher::DELAY:
            case Watcher::REPEAT:
                $expiration = (int) (\microtime(true) * self::MILLISEC_PER_SEC) + $watcher->value;
                $this->timerExpires[$watcher->id] = $expiration;
                $this->timerQueue->insert([$watcher->id, $expiration], -$expiration);
                break;

            case Watcher::SIGNAL:
                $this->enableSignal($watcher);
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disable($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            return;
        }

        $watcher = $this->watchers[$watcherIdentifier];

        switch ($watcher->type) {
            case Watcher::READABLE:
                $key = (int) $watcher->value;
                unset($this->readWatchers[$key], $this->readStreams[$key]);
                break;

            case Watcher::WRITABLE:
                $key = (int) $watcher->value;
                unset($this->writeWatchers[$key], $this->writeStreams[$key]);
                break;

            case Watcher::DELAY:
                unset($this->watchers[$watcher->id]);
                // No break

            case Watcher::REPEAT:
                unset($this->timerExpires[$watcher->id]);
                break;

            case Watcher::DEFER:
                unset($this->watchers[$watcher->id]);
                break;

            case Watcher::SIGNAL:
                $this->disableSignal($watcher);
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherIdentifier) {
        $this->disable($watcherIdentifier);
        unset($this->watchers[$watcherIdentifier], $this->unreferenced[$watcherIdentifier]);
    }

    /**
     * {@inheritdoc}
     */
    public function reference($watcherIdentifier) {
        unset($this->unreferenced[$watcherIdentifier]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            return;
        }

        $this->unreferenced[$watcherIdentifier] = $watcherIdentifier;
    }

    /**
     * {@inheritdoc}
     */
    public function info() {
        $watchers = [
            "referenced"   => \count($this->watchers) - \count($this->unreferenced),
            "unreferenced" => \count($this->unreferenced),
        ];

        $defer = $delay = $repeat = $onReadable = $onWritable = $onSignal = [
            "enabled"  => 0,
            "disabled" => 0,
        ];

        foreach ($this->watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::READABLE:
                    if (isset($this->readWatchers[(int) $watcher->value])) {
                        ++$onReadable["enabled"];
                    } else {
                        ++$onReadable["disabled"];
                    }
                    break;

                case Watcher::WRITABLE:
                    if (isset($this->writeWatchers[(int) $watcher->value])) {
                        ++$onWritable["enabled"];
                    } else {
                        ++$onWritable["disabled"];
                    }
                    break;

                case Watcher::DEFER:
                    ++$defer["enabled"];
                    break;

                case Watcher::DELAY:
                    ++$delay["enabled"];
                    break;

                case Watcher::REPEAT:
                    if (isset($this->timerExpires[$watcher->id])) {
                        ++$repeat["enabled"];
                    } else {
                        ++$repeat["disabled"];
                    }
                    break;
            }
        }

        return [
            "defer"       => $defer,
            "delay"       => $delay,
            "repeat"      => $repeat,
            "on_readable" => $onReadable,
            "on_writable" => $onWritable,
            "on_signal"   => $onSignal,
            "watchers"    => $watchers,
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