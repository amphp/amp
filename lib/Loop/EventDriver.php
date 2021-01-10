<?php

namespace Amp\Loop;

use Amp\Promise;
use function Amp\Internal\getCurrentTime;

final class EventDriver extends DriverFoundation
{
    /** @var \Event[]|null */
    private static ?array $activeSignals = null;

    private \EventBase $handle;

    /** @var \Event[] */
    private array $events = [];

    private \Closure $ioCallback;

    private \Closure $timerCallback;

    private \Closure $signalCallback;

    private array $signals = [];

    /** @var int Internal timestamp for now. */
    private int $now;

    /** @var int Loop time offset */
    private int $nowOffset;

    public function __construct()
    {
        /** @psalm-suppress TooFewArguments https://github.com/JetBrains/phpstorm-stubs/pull/763 */
        $this->handle = new \EventBase;
        $this->nowOffset = getCurrentTime();
        $this->now = \random_int(0, $this->nowOffset);
        $this->nowOffset -= $this->now;

        if (self::$activeSignals === null) {
            self::$activeSignals = &$this->signals;
        }

        /**
         * @param         $resource
         * @param         $what
         * @param Watcher $watcher
         *
         * @return void
         */
        $this->ioCallback = function ($resource, $what, Watcher $watcher): void {
            \assert(\is_resource($watcher->value));

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result instanceof Promise) {
                    Promise\rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };

        /**
         * @param         $resource
         * @param         $what
         * @param Watcher $watcher
         *
         * @return void
         */
        $this->timerCallback = function ($resource, $what, Watcher $watcher): void {
            \assert(\is_int($watcher->value));

            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            } else {
                $this->events[$watcher->id]->add($watcher->value / self::MILLISEC_PER_SEC);
            }

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->data);

                if ($result instanceof Promise) {
                    Promise\rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };

        /**
         * @param         $signum
         * @param         $what
         * @param Watcher $watcher
         *
         * @return void
         */
        $this->signalCallback = function ($signum, $what, Watcher $watcher): void {
            try {
                $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                if ($result instanceof Promise) {
                    Promise\rethrow($result);
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
        if (isset($this->handle)) {
            $this->handle->free();
            unset($this->handle);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        $active = self::$activeSignals;

        \assert($active !== null);

        foreach ($active as $event) {
            $event->del();
        }

        self::$activeSignals = &$this->signals;

        foreach ($this->signals as $event) {
            /** @psalm-suppress TooFewArguments https://github.com/JetBrains/phpstorm-stubs/pull/763 */
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
                /** @psalm-suppress TooFewArguments https://github.com/JetBrains/phpstorm-stubs/pull/763 */
                $event->add();
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
    public function getHandle(): \EventBase
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
        $this->handle->loop($blocking ? \EventBase::LOOP_ONCE : \EventBase::LOOP_ONCE | \EventBase::LOOP_NONBLOCK);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    protected function activate(array $watchers): void
    {
        $now = $this->now();

        foreach ($watchers as $watcher) {
            if (!isset($this->events[$id = $watcher->id])) {
                switch ($watcher->type) {
                    case Watcher::READABLE:
                        \assert(\is_resource($watcher->value));

                        $this->events[$id] = new \Event(
                            $this->handle,
                            $watcher->value,
                            \Event::READ | \Event::PERSIST,
                            $this->ioCallback,
                            $watcher
                        );
                        break;

                    case Watcher::WRITABLE:
                        \assert(\is_resource($watcher->value));

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
                        \assert(\is_int($watcher->value));

                        $this->events[$id] = new \Event(
                            $this->handle,
                            -1,
                            \Event::TIMEOUT,
                            $this->timerCallback,
                            $watcher
                        );
                        break;

                    case Watcher::SIGNAL:
                        \assert(\is_int($watcher->value));

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
                    \assert(\is_int($watcher->value));

                    $interval = \max(0, $watcher->expiration - $now);
                    $this->events[$id]->add($interval > 0 ? $interval / self::MILLISEC_PER_SEC : 0);
                    break;

                case Watcher::SIGNAL:
                    $this->signals[$id] = $this->events[$id];
                // no break

                default:
                    /** @psalm-suppress TooFewArguments https://github.com/JetBrains/phpstorm-stubs/pull/763 */
                    $this->events[$id]->add();
                    break;
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
            $this->events[$id]->del();

            if ($watcher->type === Watcher::SIGNAL) {
                unset($this->signals[$id]);
            }
        }
    }
}
