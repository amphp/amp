<?php

namespace Amp\Loop;

use Amp\Coroutine;
use Amp\Promise;
use React\Promise\PromiseInterface as ReactPromise;
use function Amp\Internal\getCurrentTime;
use function Amp\Promise\rethrow;

class EventDriver extends Driver
{
    /** @var \Event[]|null */
    private static $activeSignals;

    /** @var \EventBase */
    private $handle;

    /** @var \Event[] */
    private $events = [];

    /** @var callable */
    private $ioCallback;

    /** @var callable */
    private $timerCallback;

    /** @var callable */
    private $signalCallback;

    /** @var \Event[] */
    private $signals = [];

    /** @var bool */
    private $nowUpdateNeeded = false;

    /** @var int Internal timestamp for now. */
    private $now;

    /** @var int Loop time offset */
    private $nowOffset;

    public function __construct()
    {
        $this->handle = new \EventBase;
        $this->nowOffset = getCurrentTime();
        $this->now = \random_int(0, $this->nowOffset);
        $this->nowOffset -= $this->now;

        if (self::$activeSignals === null) {
            self::$activeSignals = &$this->signals;
        }

        $this->ioCallback = function ($resource, $what, Watcher $watcher) {
            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result === null) {
                    return;
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
        };

        $this->timerCallback = function ($resource, $what, Watcher $watcher) {
            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            } else {
                $this->events[$watcher->id]->add($watcher->value / self::MILLISEC_PER_SEC);
            }

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->data);

                if ($result === null) {
                    return;
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
        };

        $this->signalCallback = function ($signum, $what, Watcher $watcher) {
            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result === null) {
                    return;
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
        };
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(string $watcherId)
    {
        parent::cancel($watcherId);

        if (isset($this->events[$watcherId])) {
            $this->events[$watcherId]->free();
            unset($this->events[$watcherId]);
        }
    }

    public static function isSupported(): bool
    {
        return \extension_loaded("event");
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            if ($event !== null) { // Events may have been nulled in extension depending on destruct order.
                $event->free();
            }
        }

        // Unset here, otherwise $event->del() fails with a warning, because __destruct order isn't defined.
        // See https://github.com/amphp/amp/issues/159.
        $this->events = [];

        // Manually free the loop handle to fully release loop resources.
        // See https://github.com/amphp/amp/issues/177.
        if ($this->handle !== null) {
            $this->handle->free();
            $this->handle = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $active = self::$activeSignals;

        foreach ($active as $event) {
            $event->del();
        }

        self::$activeSignals = &$this->signals;

        foreach ($this->signals as $event) {
            $event->add();
        }

        try {
            parent::run();
        } finally {
            foreach ($this->signals as $event) {
                $event->del();
            }

            self::$activeSignals = &$active;

            foreach ($active as $event) {
                $event->add();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->handle->stop();
        parent::stop();
    }

    /**
     * {@inheritdoc}
     */
    public function now(): int
    {
        if ($this->nowUpdateNeeded) {
            $this->now = getCurrentTime() - $this->nowOffset;
            $this->nowUpdateNeeded = false;
        }

        return $this->now;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle(): \EventBase
    {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch(bool $blocking)
    {
        $this->nowUpdateNeeded = true;
        $this->handle->loop($blocking ? \EventBase::LOOP_ONCE : \EventBase::LOOP_ONCE | \EventBase::LOOP_NONBLOCK);
    }

    /**
     * {@inheritdoc}
     */
    protected function activate(array $watchers)
    {
        $now = getCurrentTime() - $this->nowOffset;

        foreach ($watchers as $watcher) {
            if (!isset($this->events[$id = $watcher->id])) {
                switch ($watcher->type) {
                    case Watcher::READABLE:
                        $this->events[$id] = new \Event(
                            $this->handle,
                            $watcher->value,
                            \Event::READ | \Event::PERSIST,
                            $this->ioCallback,
                            $watcher
                        );
                        break;

                    case Watcher::WRITABLE:
                        $this->events[$id] = new \Event(
                            $this->handle,
                            $watcher->value,
                            \Event::WRITE | \Event::PERSIST,
                            $this->ioCallback,
                            $watcher
                        );
                        break;

                    case Watcher::DELAY:
                    case Watcher::REPEAT:
                        $this->events[$id] = new \Event(
                            $this->handle,
                            -1,
                            \Event::TIMEOUT,
                            $this->timerCallback,
                            $watcher
                        );
                        break;

                    case Watcher::SIGNAL:
                        $this->events[$id] = new \Event(
                            $this->handle,
                            $watcher->value,
                            \Event::SIGNAL | \Event::PERSIST,
                            $this->signalCallback,
                            $watcher
                        );
                        break;

                    default:
                        // @codeCoverageIgnoreStart
                        throw new \Error("Unknown watcher type");
                    // @codeCoverageIgnoreEnd
                }
            }

            switch ($watcher->type) {
                case Watcher::DELAY:
                case Watcher::REPEAT:
                    $interval = $watcher->value - ($now - $this->now());
                    $this->events[$id]->add($interval > 0 ? $interval / self::MILLISEC_PER_SEC : 0);
                    break;

                case Watcher::SIGNAL:
                    $this->signals[$id] = $this->events[$id];
                // no break

                default:
                    $this->events[$id]->add();
                    break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deactivate(Watcher $watcher)
    {
        if (isset($this->events[$id = $watcher->id])) {
            $this->events[$id]->del();

            if ($watcher->type === Watcher::SIGNAL) {
                unset($this->signals[$id]);
            }
        }
    }
}
