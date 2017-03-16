<?php

namespace Amp\Loop;

use Amp\Coroutine;
use Amp\Promise;
use Amp\Internal\Watcher;
use React\Promise\PromiseInterface as ReactPromise;
use function Amp\Promise\rethrow;

class NativeDriver extends Driver {
    /** @var resource[] */
    private $readStreams = [];

    /** @var \Amp\Internal\Watcher[][] */
    private $readWatchers = [];

    /** @var resource[] */
    private $writeStreams = [];

    /** @var \Amp\Internal\Watcher[][] */
    private $writeWatchers = [];

    /** @var int[] */
    private $timerExpires = [];

    /** @var \SplPriorityQueue */
    private $timerQueue;

    /** @var \Amp\Internal\Watcher[][] */
    private $signalWatchers = [];

    /** @var bool */
    private $signalHandling;

    public function __construct() {
        $this->timerQueue = new \SplPriorityQueue();
        $this->signalHandling = \extension_loaded("pcntl");
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Amp\Loop\UnsupportedFeatureException If the pcntl extension is not available.
     */
    public function onSignal(int $signo, callable $callback, $data = null): string {
        if (!$this->signalHandling) {
            throw new UnsupportedFeatureException("Signal handling requires the pcntl extension");
        }

        return parent::onSignal($signo, $callback, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return null;
    }

    protected function dispatch(bool $blocking) {
        $this->selectStreams(
            $this->readStreams,
            $this->writeStreams,
            $blocking ? $this->getTimeout() : 0
        );

        if (!empty($this->timerExpires)) {
            $time = (int) (\microtime(true) * self::MILLISEC_PER_SEC);

            while (!$this->timerQueue->isEmpty()) {
                list($watcher, $expiration) = $this->timerQueue->top();

                $id = $watcher->id;

                if (!isset($this->timerExpires[$id]) || $expiration !== $this->timerExpires[$id]) {
                    $this->timerQueue->extract(); // Timer was removed from queue.
                    continue;
                }

                if ($this->timerExpires[$id] > $time) { // Timer at top of queue has not expired.
                    break;
                }

                $this->timerQueue->extract();

                if ($watcher->type & Watcher::REPEAT) {
                    $this->activate([$watcher]);
                } else {
                    $this->cancel($id);
                }

                try {
                    // Execute the timer.
                    $result = ($watcher->callback)($id, $watcher->data);

                    if ($result instanceof \Generator) {
                        $result = new Coroutine($result);
                    }

                    if ($result instanceof Promise || $result instanceof ReactPromise) {
                        rethrow($result);
                    }
                } catch (\Throwable $exception) {
                    $this->error($exception);
                }
            }
        }

        if ($this->signalHandling) {
            \pcntl_signal_dispatch();
        }
    }

    /**
     * @param resource[] $read
     * @param resource[] $write
     * @param int        $timeout
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
            if (!@\stream_select($read, $write, $except, $seconds, $microseconds)) {
                return;
            }

            foreach ($read as $stream) {
                $streamId = (int) $stream;
                if (!isset($this->readWatchers[$streamId])) {
                    continue; // All read watchers disabled.
                }

                foreach ($this->readWatchers[$streamId] as $watcher) {
                    if (!isset($this->readWatchers[$streamId][$watcher->id])) {
                        continue; // Watcher disabled by another IO watcher.
                    }

                    try {
                        $result = ($watcher->callback)($watcher->id, $stream, $watcher->data);

                        if ($result === null) {
                            continue;
                        }

                        if ($result instanceof \Generator) {
                            $result = new Coroutine($result);
                        }

                        if ($result instanceof Promise || $result instanceof ReactPromise) {
                            rethrow($result);
                        }
                    } catch (\Throwable $exception) {
                        $this->error($exception);
                    }
                }
            }

            foreach ($write as $stream) {
                $streamId = (int) $stream;
                if (!isset($this->writeWatchers[$streamId])) {
                    continue; // All write watchers disabled.
                }

                foreach ($this->writeWatchers[$streamId] as $watcher) {
                    if (!isset($this->writeWatchers[$streamId][$watcher->id])) {
                        continue; // Watcher disabled by another IO watcher.
                    }

                    try {
                        $result = ($watcher->callback)($watcher->id, $stream, $watcher->data);

                        if ($result === null) {
                            continue;
                        }

                        if ($result instanceof \Generator) {
                            $result = new Coroutine($result);
                        }

                        if ($result instanceof Promise || $result instanceof ReactPromise) {
                            rethrow($result);
                        }
                    } catch (\Throwable $exception) {
                        $this->error($exception);
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
     * {@inheritdoc}
     */
    protected function activate(array $watchers) {
        foreach ($watchers as $watcher) {
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
                    $expiration = (int) (\microtime(true) * self::MILLISEC_PER_SEC) + $watcher->value;
                    $this->timerExpires[$watcher->id] = $expiration;
                    $this->timerQueue->insert([$watcher, $expiration], -$expiration);
                    break;

                case Watcher::SIGNAL:
                    if (!isset($this->signalWatchers[$watcher->value])) {
                        if (!@\pcntl_signal($watcher->value, [$this, 'handleSignal'])) {
                            $message = "Failed to register signal handler";
                            if ($error = \error_get_last()) {
                                $message .= \sprintf("; Errno: %d; %s", $error["type"], $error["message"]);
                            }
                            throw new \Error($message);
                        }
                    }

                    $this->signalWatchers[$watcher->value][$watcher->id] = $watcher;
                    break;

                default:
                    // @codeCoverageIgnoreStart
                    throw new \Error("Unknown watcher type");
                    // @codeCoverageIgnoreEnd
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deactivate(Watcher $watcher) {
        switch ($watcher->type) {
            case Watcher::READABLE:
                $streamId = (int) $watcher->value;
                unset($this->readWatchers[$streamId][$watcher->id]);
                if (empty($this->readWatchers[$streamId])) {
                    unset($this->readWatchers[$streamId], $this->readStreams[$streamId]);
                }
                break;

            case Watcher::WRITABLE:
                $streamId = (int) $watcher->value;
                unset($this->writeWatchers[$streamId][$watcher->id]);
                if (empty($this->writeWatchers[$streamId])) {
                    unset($this->writeWatchers[$streamId], $this->writeStreams[$streamId]);
                }
                break;

            case Watcher::DELAY:
            case Watcher::REPEAT:
                unset($this->timerExpires[$watcher->id]);
                break;

            case Watcher::SIGNAL:
                if (isset($this->signalWatchers[$watcher->value])) {
                    unset($this->signalWatchers[$watcher->value][$watcher->id]);

                    if (empty($this->signalWatchers[$watcher->value])) {
                        unset($this->signalWatchers[$watcher->value]);
                        @\pcntl_signal($watcher->value, \SIG_DFL);
                    }
                }
                break;

            default:
                // @codeCoverageIgnoreStart
                throw new \Error("Unknown watcher type");
                // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @param int $signo
     */
    private function handleSignal(int $signo) {
        foreach ($this->signalWatchers[$signo] as $watcher) {
            if (!isset($this->signalWatchers[$signo][$watcher->id])) {
                continue;
            }

            try {
                $result = ($watcher->callback)($watcher->id, $signo, $watcher->data);

                if ($result === null) {
                    continue;
                }

                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }

                if ($result instanceof Promise || $result instanceof ReactPromise) {
                    rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        }
    }
}
