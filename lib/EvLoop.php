<?php

namespace Amp\Loop;

use Amp\Loop\Internal\Watcher;

class EvLoop extends Loop {
    /**
     * @var \EvLoop
     */
    private $handle;

    /**
     * @var \EvWatcher[]
     */
    private $events = [];

    /**
     * @var callable
     */
    private $ioCallback;

    /**
     * @var callable
     */
    private $timerCallback;
    
    /**
     * @var callable
     */
    private $signalCallback;

    public static function enabled() {
        return \extension_loaded("ev");
    }

    public function __construct() {
        $this->handle = new \EvLoop;

        $this->ioCallback = function (\EvIO $event) {
            /** @var \Amp\Loop\Internal\Watcher $watcher */
            $watcher = $event->data;

            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->value, $watcher->data);
        };

        $this->timerCallback = function (\EvTimer $event) {
            /** @var \Amp\Loop\Internal\Watcher $watcher */
            $watcher = $event->data;

            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            }

            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->data);
        };

        $this->signalCallback = function (\EvSignal $event) {
            /** @var \Amp\Loop\Internal\Watcher $watcher */
            $watcher = $event->data;

            $callback = $watcher->callback;
            $callback($watcher->id, $event->signum, $watcher->data);
        };
    }

    /**
     * {@inheritdoc}
     */
    public function stop() {
        $this->handle->stop();
        parent::stop();
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking) {
        $this->handle->run($blocking ? \Ev::RUN_ONCE : \Ev::RUN_ONCE | \Ev::RUN_NOWAIT);
    }

    /**
     * {@inheritdoc}
     */
    protected function activate(array $watchers) {
        foreach ($watchers as $watcher) {
            if (!isset($this->events[$id = $watcher->id])) {
                switch ($watcher->type) {
                    case Watcher::READABLE:
                        $this->events[$id] = $this->handle->io($watcher->value, \Ev::READ, $this->ioCallback, $watcher);
                        break;

                    case Watcher::WRITABLE:
                        $this->events[$id] = $this->handle->io($watcher->value, \Ev::WRITE, $this->ioCallback, $watcher);
                        break;

                    case Watcher::DELAY:
                    case Watcher::REPEAT:
                        $interval = $watcher->value / self::MILLISEC_PER_SEC;
                        $this->events[$id] = $this->handle->timer(
                            $interval,
                            $watcher->type & Watcher::REPEAT ? $interval : 0,
                            $this->timerCallback,
                            $watcher
                        );
                        break;

                    case Watcher::SIGNAL:
                        $this->events[$id] = $this->handle->signal($watcher->value, $this->signalCallback, $watcher);
                        break;

                    default:
                        throw new \DomainException("Unknown watcher type");
                }

                if (!$watcher->referenced) {
                    $this->events[$id]->keepalive(false);
                }
            } else {
                $this->events[$id]->start();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Watcher $watcher) {
        if (isset($this->events[$id = $watcher->id])) {
            $this->events[$id]->stop();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherIdentifier) {
        parent::cancel($watcherIdentifier);
        unset($this->events[$watcherIdentifier]);
    }

    /**
     * {@inheritdoc}
     */
    public function reference($watcherIdentifier) {
        parent::reference($watcherIdentifier);

        if (isset($this->events[$watcherIdentifier])) {
            $this->events[$watcherIdentifier]->keepalive(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unreference($watcherIdentifier) {
        parent::unreference($watcherIdentifier);

        if (isset($this->events[$watcherIdentifier])) {
            $this->events[$watcherIdentifier]->keepalive(false);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return $this->handle;
    }
}
