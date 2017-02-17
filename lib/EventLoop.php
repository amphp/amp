<?php

namespace Amp\Loop;

use Amp\Loop\Internal\Watcher;

class EventLoop extends Loop {
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
    
    /** @var \Event[]|null */
    private static $activeSignals;

    public static function supported() {
        return \extension_loaded("event");
    }

    public function __construct() {
        $this->handle = new \EventBase;
    
        if (self::$activeSignals === null) {
            self::$activeSignals = &$this->signals;
        }
        
        $this->ioCallback = function ($resource, $what, Watcher $watcher) {
            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->value, $watcher->data);
        };

        $this->timerCallback = function ($resource, $what, Watcher $watcher) {
            if ($watcher->type & Watcher::DELAY) {
                $this->cancel($watcher->id);
            }

            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->data);
        };

        $this->signalCallback = function ($signum, $what, Watcher $watcher) {
            $callback = $watcher->callback;
            $callback($watcher->id, $watcher->value, $watcher->data);
        };
    }
    
    public function __destruct() {
        foreach ($this->events as $event) {
            $event->free();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function run() {
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
    public function stop() {
        $this->handle->stop();
        parent::stop();
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking) {
        $this->handle->loop($blocking ? \EventBase::LOOP_ONCE : \EventBase::LOOP_ONCE | \EventBase::LOOP_NONBLOCK);
    }

    /**
     * {@inheritdoc}
     */
    protected function activate(array $watchers) {
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
                        $flags = \Event::TIMEOUT;
                        if ($watcher->type === Watcher::REPEAT) {
                            $flags |= \Event::PERSIST;
                        }
                        $this->events[$id] = new \Event(
                            $this->handle,
                            -1,
                            $flags,
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
                        throw new \DomainException("Unknown watcher type");
                }
            }
            
            switch ($watcher->type) {
                case Watcher::DELAY:
                case Watcher::REPEAT:
                    $this->events[$id]->add($watcher->value / self::MILLISEC_PER_SEC);
                    break;
                    
                case Watcher::SIGNAL:
                    $this->signals[$id] = $this->events[$id];
                    // No break
                
                default:
                    $this->events[$id]->add();
                    break;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deactivate(Watcher $watcher) {
        if (isset($this->events[$id = $watcher->id])) {
            $this->events[$id]->del();
            
            if ($watcher->type === Watcher::SIGNAL) {
                unset($this->signals[$id]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($watcherIdentifier) {
        parent::cancel($watcherIdentifier);
        
        if (isset($this->events[$watcherIdentifier])) {
            $this->events[$watcherIdentifier]->free();
            unset($this->events[$watcherIdentifier]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getHandle() {
        return $this->handle;
    }
}
