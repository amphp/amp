<?php

namespace Amp\Loop;

use Amp\Loop\Internal\Watcher;
use Interop\Async\Loop\Driver;
use Interop\Async\Loop\InvalidWatcherException;

abstract class Loop extends Driver {
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
     * @var \Amp\Loop\Internal\Watcher[]
     */
    private $nextTickQueue = [];

    /**
     * @var callable
     */
    private $errorHandler;

    /**
     * @var bool
     */
    private $running = false;

    /**
     * {@inheritdoc}
     */
    public function run() {
        $previous = $this->running;
        $this->running = true;

        try {
            while ($this->running) {
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
        $this->running = false;
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
        $this->deferQueue = \array_merge($this->deferQueue, $this->nextTickQueue);
        $this->nextTickQueue = [];

        $queue = $this->enableQueue;
        $this->enableQueue = [];
        $this->activate($queue);

        try {
            foreach ($this->deferQueue as $watcher) {
                if (!isset($this->deferQueue[$watcher->id])) {
                    continue; // Watcher disabled by another defer watcher.
                }

                unset($this->watchers[$watcher->id], $this->deferQueue[$watcher->id]);

                $callback = $watcher->callback;
                $callback($watcher->id, $watcher->data);
            }

            $this->dispatch(empty($this->nextTickQueue) && empty($this->enableQueue) && $this->running);

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
     * Dispatches any pending read/write, timer, and signal events.
     *
     * @param bool $blocking
     */
    abstract protected function dispatch($blocking);

    /**
     * Activates (enables) all the given watchers.
     *
     * @param \Amp\Loop\Internal\Watcher[] $watchers
     */
    abstract protected function activate(array $watchers);

    /**
     * Deactivates (disables) the given watcher.
     *
     * @param \Amp\Loop\Internal\Watcher $watcher
     */
    abstract protected function deactivate(Watcher $watcher);

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
        $this->nextTickQueue[$watcher->id] = $watcher;

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
    public function enable($watcherIdentifier) {
        if (!isset($this->watchers[$watcherIdentifier])) {
            throw new InvalidWatcherException("Cannot enable an invalid watcher identifier");
        }

        $watcher = $this->watchers[$watcherIdentifier];

        if ($watcher->enabled) {
            return; // Watcher already enabled.
        }

        $watcher->enabled = true;

        switch ($watcher->type) {
            case Watcher::DEFER:
                $this->nextTickQueue[$watcher->id] = $watcher;
                break;

            default:
                $this->enableQueue[$watcher->id] = $watcher;
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

        if (!$watcher->enabled) {
            return; // Watcher already disabled.
        }

        $watcher->enabled = false;
        $id = $watcher->id;

        switch ($watcher->type) {
            case Watcher::DEFER:
                if (isset($this->nextTickQueue[$id])) {
                    // Watcher was only queued to be enabled.
                    unset($this->nextTickQueue[$id]);
                } else {
                    unset($this->deferQueue[$id]);
                }
                break;

            default:
                if (isset($this->enableQueue[$id])) {
                    // Watcher was only queued to be enabled.
                    unset($this->enableQueue[$id]);
                } else {
                    $this->deactivate($watcher);
                }
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherIdentifier) {
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
    public function setErrorHandler(callable $callback = null) {
        $previous = $this->errorHandler;
        $this->errorHandler = $callback;
        return $previous;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo() {
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
     * Returns the same array of data as getInfo().
     *
     * @return array
     */
    public function __debugInfo() {
        return $this->getInfo();
    }
}
