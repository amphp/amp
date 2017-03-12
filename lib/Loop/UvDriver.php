<?php

namespace Amp\Loop;

use Amp\Coroutine;
use Amp\Promise;
use Amp\Internal\Watcher;
use function Amp\rethrow;

class UvDriver extends Driver {
    /** @var resource A uv_loop resource created with uv_loop_new() */
    private $handle;

    /** @var resource[] */
    private $events = [];

    /** @var \Amp\Internal\Watcher[]|\Amp\Internal\Watcher[][] */
    private $watchers = [];

    /** @var resource[] */
    private $read = [];

    /** @var resource[] */
    private $write = [];

    /** @var callable */
    private $ioCallback;

    /** @var callable */
    private $timerCallback;

    /** @var callable */
    private $signalCallback;

    public function __construct() {
        $this->handle = \uv_loop_new();

        $this->ioCallback = function ($event, $status, $events, $resource) {
            switch ($status) {
                case 0: // OK
                    break;

                // If $status is a severe error, stop the poll and throw an exception.
                case \UV::EACCES:
                case \UV::EBADF:
                case \UV::EINVAL:
                case \UV::ENOTSOCK:
                    throw new \RuntimeException(
                        \sprintf("UV_%s: %s", \uv_err_name($status), \ucfirst(\uv_strerror($status)))
                    );

                default: // Ignore other (probably) trivial warnings and continuing polling.
                    return;
            }

            $watchers = $this->watchers[(int) $event];

            foreach ($watchers as $watcher) {
                $callback = $watcher->callback;
                $result = $callback($watcher->id, $resource, $watcher->data);

                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }

                if ($result instanceof Promise) {
                    rethrow($result);
                }
            }
        };

        $this->timerCallback = function ($event) {
            $watcher = $this->watchers[(int) $event];

            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            }

            $callback = $watcher->callback;
            $result = $callback($watcher->id, $watcher->data);

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise) {
                rethrow($result);
            }
        };

        $this->signalCallback = function ($event, $signo) {
            $watcher = $this->watchers[(int) $event];

            $callback = $watcher->callback;
            $result = $callback($watcher->id, $signo, $watcher->data);

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise) {
                rethrow($result);
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(string $watcherId) {
        parent::cancel($watcherId);

        if (!isset($this->events[$watcherId])) {
            return;
        }

        $event = $this->events[$watcherId];

        if (empty($this->watchers[(int) $event])) {
            \uv_close($event);
        }

        unset($this->events[$watcherId]);
    }

    public static function supported(): bool {
        return \extension_loaded("uv");
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return $this->handle;
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch(bool $blocking) {
        \uv_run($this->handle, $blocking ? \UV::RUN_ONCE : \UV::RUN_NOWAIT);
    }

    /**
     * {@inheritdoc}
     */
    protected function activate(array $watchers) {
        foreach ($watchers as $watcher) {
            $id = $watcher->id;

            switch ($watcher->type) {
                case Watcher::READABLE:
                    $streamId = (int) $watcher->value;

                    if (isset($this->read[$streamId])) {
                        $event = $this->read[$streamId];
                    } elseif (isset($this->events[$id])) {
                        $event = $this->read[$streamId] = $this->events[$id];
                    } else {
                        $event = $this->read[$streamId] = \uv_poll_init_socket($this->handle, $watcher->value);
                    }

                    $this->events[$id] = $event;
                    $this->watchers[(int) $event][$id] = $watcher;

                    if (!\uv_is_active($event)) {
                        \uv_poll_start($event, \UV::READABLE, $this->ioCallback);
                    }
                    break;

                case Watcher::WRITABLE:
                    $streamId = (int) $watcher->value;

                    if (isset($this->write[$streamId])) {
                        $event = $this->write[$streamId];
                    } elseif (isset($this->events[$id])) {
                        $event = $this->write[$streamId] = $this->events[$id];
                    } else {
                        $event = $this->write[$streamId] = \uv_poll_init_socket($this->handle, $watcher->value);
                    }

                    $this->events[$id] = $event;
                    $this->watchers[(int) $event][$id] = $watcher;


                    if (!\uv_is_active($event)) {
                        \uv_poll_start($event, \UV::WRITABLE, $this->ioCallback);
                    }
                    break;

                case Watcher::DELAY:
                case Watcher::REPEAT:
                    if (isset($this->events[$id])) {
                        $event = $this->events[$id];
                    } else {
                        $event = $this->events[$id] = \uv_timer_init($this->handle);
                    }

                    $this->watchers[(int) $event] = $watcher;

                    \uv_timer_start(
                        $event,
                        $watcher->value,
                        $watcher->type & Watcher::REPEAT ? $watcher->value : 0,
                        $this->timerCallback
                    );
                    break;

                case Watcher::SIGNAL:
                    if (isset($this->events[$id])) {
                        $event = $this->events[$id];
                    } else {
                        $event = $this->events[$id] = \uv_signal_init($this->handle);
                    }

                    $this->watchers[(int) $event] = $watcher;

                    \uv_signal_start($event, $this->signalCallback, $watcher->value);
                    break;

                default:
                    throw new \DomainException("Unknown watcher type");
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deactivate(Watcher $watcher) {
        $id = $watcher->id;

        if (!isset($this->events[$id])) {
            return;
        }

        $event = $this->events[$id];
        $eventId = (int) $event;

        switch ($watcher->type) {
            case Watcher::READABLE:
                unset($this->watchers[$eventId][$id]);

                if (empty($this->watchers[$eventId])) {
                    unset($this->watchers[$eventId]);
                    unset($this->read[(int) $watcher->value]);
                    if (\uv_is_active($event)) {
                        \uv_poll_stop($event);
                    }
                }
                break;

            case Watcher::WRITABLE:
                unset($this->watchers[$eventId][$id]);

                if (empty($this->watchers[$eventId])) {
                    unset($this->watchers[$eventId]);
                    unset($this->write[(int) $watcher->value]);
                    if (\uv_is_active($event)) {
                        \uv_poll_stop($event);
                    }
                }
                break;

            case Watcher::DELAY:
            case Watcher::REPEAT:
                unset($this->watchers[$eventId]);
                if (\uv_is_active($event)) {
                    \uv_timer_stop($event);
                }
                break;

            case Watcher::SIGNAL:
                unset($this->watchers[$eventId]);
                if (\uv_is_active($event)) {
                    \uv_signal_stop($event);
                }
                break;

            default:
                throw new \DomainException("Unknown watcher type");
        }
    }
}
