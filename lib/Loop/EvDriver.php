<?php /** @noinspection PhpComposerExtensionStubsInspection */

namespace Amp\Loop;

use function Amp\Internal\getCurrentTime;

final class EvDriver extends DriverFoundation
{
    /** @var \EvSignal[]|null */
    private static ?array $activeSignals = null;

    public static function isSupported(): bool
    {
        return \extension_loaded("ev");
    }

    private \EvLoop $handle;

    /** @var \EvWatcher[] */
    private array $events = [];

    private \Closure $ioCallback;

    private \Closure $timerCallback;

    private \Closure $signalCallback;

    /** @var \EvSignal[] */
    private array $signals = [];

    /** @var int Internal timestamp for now. */
    private int $now;

    /** @var int Loop time offset */
    private int $nowOffset;

    public function __construct()
    {
        $this->handle = new \EvLoop;
        $this->nowOffset = getCurrentTime();
        $this->now = \random_int(0, $this->nowOffset);
        $this->nowOffset -= $this->now;

        if (self::$activeSignals === null) {
            self::$activeSignals = &$this->signals;
        }

        /**
         * @param \EvIO $event
         *
         * @return void
         */
        $this->ioCallback = function (\EvIO $event): void {
            /** @var Watcher $watcher */
            $watcher = $event->data;

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result instanceof \Awaitable) {
                    $this->rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };

        /**
         * @param \EvTimer $event
         *
         * @return void
         */
        $this->timerCallback = function (\EvTimer $event): void {
            /** @var Watcher $watcher */
            $watcher = $event->data;

            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            } elseif ($watcher->value === 0) {
                // Disable and re-enable so it's not executed repeatedly in the same tick
                // See https://github.com/amphp/amp/issues/131
                $this->disable($watcher->id);
                $this->enable($watcher->id);
            }

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->data);

                if ($result instanceof \Awaitable) {
                    $this->rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };

        /**
         * @param \EvSignal $event
         *
         * @return void
         */
        $this->signalCallback = function (\EvSignal $event): void {
            /** @var Watcher $watcher */
            $watcher = $event->data;

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result instanceof \Awaitable) {
                    $this->rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(string $watcherId): void
    {
        parent::cancel($watcherId);
        unset($this->events[$watcherId]);
    }

    public function __destruct()
    {
        foreach ($this->events as $event) {
            /** @psalm-suppress all */
            if ($event !== null) { // Events may have been nulled in extension depending on destruct order.
                $event->stop();
            }
        }

        // We need to clear all references to events manually, see
        // https://bitbucket.org/osmanov/pecl-ev/issues/31/segfault-in-ev_timer_stop
        $this->events = [];
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        $active = self::$activeSignals;

        \assert($active !== null);

        foreach ($active as $event) {
            $event->stop();
        }

        self::$activeSignals = &$this->signals;

        foreach ($this->signals as $event) {
            $event->start();
        }

        try {
            parent::run();
        } finally {
            foreach ($this->signals as $event) {
                $event->stop();
            }

            self::$activeSignals = &$active;

            foreach ($active as $event) {
                $event->start();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(): void
    {
        $this->handle->stop();
        parent::stop();
    }

    /**
     * {@inheritdoc}
     */
    public function now(): int
    {
        $this->now = getCurrentTime() - $this->nowOffset;

        return $this->now;
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle(): \EvLoop
    {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function dispatch(bool $blocking): void
    {
        $this->handle->run($blocking ? \Ev::RUN_ONCE : \Ev::RUN_ONCE | \Ev::RUN_NOWAIT);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function activate(array $watchers): void
    {
        $this->handle->nowUpdate();
        $now = $this->now();

        foreach ($watchers as $watcher) {
            if (!isset($this->events[$id = $watcher->id])) {
                switch ($watcher->type) {
                    case Watcher::READABLE:
                        \assert(\is_resource($watcher->value));

                        $this->events[$id] = $this->handle->io($watcher->value, \Ev::READ, $this->ioCallback, $watcher);
                        break;

                    case Watcher::WRITABLE:
                        \assert(\is_resource($watcher->value));

                        $this->events[$id] = $this->handle->io(
                            $watcher->value,
                            \Ev::WRITE,
                            $this->ioCallback,
                            $watcher
                        );
                        break;

                    case Watcher::DELAY:
                    case Watcher::REPEAT:
                        \assert(\is_int($watcher->value));

                        $interval = $watcher->value / self::MILLISEC_PER_SEC;
                        $this->events[$id] = $this->handle->timer(
                            \max(0, ($watcher->expiration - $now) / self::MILLISEC_PER_SEC),
                            ($watcher->type & Watcher::REPEAT) ? $interval : 0,
                            $this->timerCallback,
                            $watcher
                        );
                        break;

                    case Watcher::SIGNAL:
                        \assert(\is_int($watcher->value));

                        $this->events[$id] = $this->handle->signal($watcher->value, $this->signalCallback, $watcher);
                        break;

                    default:
                        // @codeCoverageIgnoreStart
                        throw new \Error("Unknown watcher type");
                    // @codeCoverageIgnoreEnd
                }
            } else {
                $this->events[$id]->start();
            }

            if ($watcher->type === Watcher::SIGNAL) {
                /** @psalm-suppress PropertyTypeCoercion */
                $this->signals[$id] = $this->events[$id];
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function deactivate(Watcher $watcher): void
    {
        if (isset($this->events[$id = $watcher->id])) {
            $this->events[$id]->stop();

            if ($watcher->type === Watcher::SIGNAL) {
                unset($this->signals[$id]);
            }
        }
    }
}
