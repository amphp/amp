<?php

namespace Amp\Loop;

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
        $this->signalHandling = extension_loaded('pcntl');
    }
    
    /**
     * {@inheritdoc}
     * 
     * @throws \Amp\Loop\Exception\AlreadyRunningException
     */
    public function run() {
        if ($this->running) {
            throw new Exception\AlreadyRunningException('Cannot run loop recursively; loop already running');
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

        return count($this->watchers) === count($this->unreferenced);
    }

    /**
     * Executes a single tick of the event loop.
     */
    private function tick() {
        try {
            $this->invokeDeferred();

            $this->selectStreams($this->readStreams, $this->writeStreams, $this->getTimeout());

            $this->invokeTimers();

            if ($this->signalHandling) {
                pcntl_signal_dispatch();
            }
        } catch (\Throwable $exception) {
            if (null === $this->errorHandler) {
                throw $exception;
            }

            $errorHandler = $this->errorHandler;
            $errorHandler($exception);
        } catch (\Exception $exception) { // Remove when PHP 5.x support is no longer needed.
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
            $count = @stream_select($read, $write, $except, $seconds, $microseconds);

            if ($count) {
                foreach ($read as $stream) {
                    $key = (int) $stream;
                    if (isset($this->readStreams[$key], $this->readWatchers[$key])) {
                        /** @var \Amp\Loop\Internal\Io $watcher */
                        $watcher = $this->watchers[$this->readWatchers[$key]];

                        $callback = $watcher->callback;
                        $callback($watcher->id, $stream, $watcher->data);
                    }
                }

                foreach ($write as $stream) {
                    $key = (int) $stream;
                    if (isset($this->writeStreams[$key], $this->writeWatchers[$key])) {
                        /** @var \Amp\Loop\Internal\Io $watcher */
                        $watcher = $this->watchers[$this->writeWatchers[$key]];

                        $callback = $watcher->callback;
                        $callback($watcher->id, $stream, $watcher->data);
                    }
                }
            }

            return;
        }

        if ($timeout > 0) { // Otherwise sleep with usleep() if $timeout > 0.
            usleep($timeout * self::MICROSEC_PER_SEC);
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

            $timeout -= (int) (microtime(true) * self::MILLISEC_PER_SEC);

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
        $count = 0;

        try {
            foreach ($this->deferQueue as $id) {
                ++$count;

                if (!isset($this->watchers[$id])) {
                    continue;
                }

                /** @var \Amp\Loop\Internal\Defer $watcher */
                $watcher = $this->watchers[$id];
                unset($this->watchers[$id], $this->unreferenced[$id]);

                $callback = $watcher->callback;
                $callback($watcher->id, $watcher->data);
            }
        } finally {
            if ($count === count($this->deferQueue)) {
                $this->deferQueue = [];
            } else {
                $this->deferQueue = array_slice($this->deferQueue, $count);
            }
        }
    }

    /**
     * Invokes all pending delay and repeat watchers.
     */
    private function invokeTimers() {
        $time = (int) (microtime(true) * self::MILLISEC_PER_SEC);
    
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
        
            /** @var \Amp\Loop\Internal\Timer $timer */
            $timer = $this->watchers[$id];
            
            if ($timer instanceof Internal\Repeat) {
                $timeout = $time + $timer->interval;
                $this->timerQueue->insert([$id, $timeout], -$timeout);
                $this->timerExpires[$id] = $timeout;
            } else {
                unset($this->watchers[$id], $this->timerExpires[$id], $this->unreferenced[$id]);
            }
        
            // Execute the timer.
            $callback = $timer->callback;
            $callback($timer->id, $timer->data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function defer(callable $callback, $data = null) {
        $watcher = new Internal\Defer($callback, $data);

        $this->watchers[$watcher->id] = $watcher;
        $this->deferQueue[] = $watcher->id;

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function delay($delay, callable $callback, $data = null) {
        return $this->timer(new Internal\Delay($delay, $callback, $data));
    }

    /**
     * {@inheritdoc}
     */
    public function repeat($interval, callable $callback, $data = null) {
        return $this->timer(new Internal\Repeat($interval, $callback, $data));
    }

    /**
     * @param \Amp\Loop\Internal\Timer $watcher
     *
     * @return string Watcher identifier.
     */
    private function timer(Internal\Timer $watcher) {
        $this->watchers[$watcher->id] = $watcher;

        $expiration = (int) (microtime(true) * self::MILLISEC_PER_SEC) + $watcher->interval;

        $this->timerExpires[$watcher->id] = $expiration;
        $this->timerQueue->insert([$watcher->id, $expiration], -$expiration);

        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onReadable($stream, callable $callback, $data = null) {
        $watcher = new Internal\Read($stream, $callback, $data);
    
        $this->watchers[$watcher->id] = $watcher;
        $this->readWatchers[$watcher->key] = $watcher->id;
        $this->readStreams[$watcher->key] = $watcher->stream;
    
        return $watcher->id;
    }

    /**
     * {@inheritdoc}
     */
    public function onWritable($stream, callable $callback, $data = null) {
        $watcher = new Internal\Write($stream, $callback, $data);

        $this->watchers[$watcher->id] = $watcher;
        $this->writeWatchers[$watcher->key] = $watcher->id;
        $this->writeStreams[$watcher->key] = $watcher->stream;

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
            throw new UnsupportedFeatureException('Signal handling requires the pcntl extension');
        }

        $watcher = new Internal\Signal($signo, $callback, $data);
        $this->enableSignal($watcher);
        $this->watchers[$watcher->id] = $watcher;

        return $watcher->id;
    }

    /**
     * @param \Amp\Loop\Internal\Signal $watcher
     *
     * @throws \Amp\Loop\Exception\SignalHandlerException If creating the backend signal handler fails.
     */
    private function enableSignal(Internal\Signal $watcher) {
        if (!isset($this->signalWatchers[$watcher->signo])) {
            if (!@\pcntl_signal($watcher->signo, function ($signo) {
                foreach ($this->signalWatchers[$signo] as $id) {
                    if (!isset($this->watchers[$id])) {
                        continue;
                    }

                    /** @var \Amp\Loop\Internal\Signal $watcher */
                    $watcher = $this->watchers[$id];

                    $callback = $watcher->callback;
                    $callback($watcher->id, $watcher->signo, $watcher->data);
                }
            })) {
                throw new Exception\SignalHandlerException('Failed to register signal handler');
            }

            $this->signalWatchers[$watcher->signo] = [];
        }

        $this->signalWatchers[$watcher->signo][$watcher->id] = $watcher->id;
    }

    /**
     * @param \Amp\Loop\Internal\Signal $watcher
     */
    private function disableSignal(Internal\Signal $watcher) {
        if (isset($this->signalWatchers[$watcher->signo])) {
            unset($this->signalWatchers[$watcher->signo][$watcher->id]);

            if (empty($this->signalWatchers[$watcher->signo])) {
                unset($this->signalWatchers[$watcher->signo]);
                @\pcntl_signal($watcher->signo, \SIG_DFL);
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

        if ($watcher instanceof Internal\Read) {
            $this->readWatchers[$watcher->key] = $watcher->id;
            $this->readStreams[$watcher->key] = $watcher->stream;
        } elseif ($watcher instanceof Internal\Write) {
            $this->writeWatchers[$watcher->key] = $watcher->id;
            $this->writeStreams[$watcher->key] = $watcher->stream;
        } elseif ($watcher instanceof Internal\Timer) {
            $expiration = (int) (microtime(true) * self::MILLISEC_PER_SEC) + $watcher->interval;
            $this->timerExpires[$watcher->id] = $expiration;
            $this->timerQueue->insert([$watcher->id, $expiration], -$expiration);
        } elseif ($watcher instanceof Internal\Signal) {
            $this->enableSignal($watcher);
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

        if ($watcher instanceof Internal\Read) {
            unset($this->readWatchers[$watcher->key], $this->readStreams[$watcher->key]);
        } elseif ($watcher instanceof Internal\Write) {
            unset($this->writeWatchers[$watcher->key], $this->writeStreams[$watcher->key]);
        } elseif ($watcher instanceof Internal\Timer) {
            unset($this->timerExpires[$watcher->id]);
            if ($watcher instanceof Internal\Delay) {
                unset($this->watchers[$watcher->id]);
            }
        } elseif ($watcher instanceof Internal\Defer) {
            unset($this->watchers[$watcher->id], $this->deferQueue[$watcher->id]);
        } elseif ($watcher instanceof Internal\Signal) {
            $this->disableSignal($watcher);
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
            'referenced'   => count($this->watchers) - count($this->unreferenced),
            'unreferenced' => count($this->unreferenced),
        ];

        $defer = $delay = $repeat = $onReadable = $onWritable = $onSignal = [
            'enabled'  => 0,
            'disabled' => 0,
        ];

        foreach ($this->watchers as $watcher) {
            if ($watcher instanceof Internal\Read) {
                if (isset($this->readWatchers[$watcher->key])) {
                    ++$onReadable['enabled'];
                } else {
                    ++$onReadable['disabled'];
                }
            } elseif ($watcher instanceof Internal\Write) {
                if (isset($this->writeWatchers[$watcher->key])) {
                    ++$onReadable['enabled'];
                } else {
                    ++$onReadable['disabled'];
                }
            } elseif ($watcher instanceof Internal\Delay) {
                ++$delay['enabled'];
            } elseif ($watcher instanceof Internal\Repeat) {
                if (isset($this->timerExpires[$watcher->id])) {
                    ++$repeat['enabled'];
                } else {
                    ++$repeat['disabled'];
                }
            } elseif ($watcher instanceof Internal\Defer) {
                ++$delay['enabled'];
            }
        }

        return [
            'defer'       => $defer,
            'delay'       => $delay,
            'repeat'      => $repeat,
            'on_readable' => $onReadable,
            'on_writable' => $onWritable,
            'on_signal'   => $onSignal,
            'watchers'    => $watchers,
            'running'     => $this->running,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return null;
    }
}