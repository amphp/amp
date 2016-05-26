<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Coroutine;
use Amp\Failure;
use Amp\Future;

trait Producer {
    /**
     * @var Subscription[]
     */
    private $subscriptions = [];

    /**
     * @var \Amp\Future|null
     */
    private $waiting;

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var \Throwable|\Exception|null
     */
    private $exception;

    /**
     * @var callable
     */
    private $unsubscribe;

    public function __construct() {
        $this->waiting = new Future;

        $this->unsubscribe = function (Subscription $subscription) {
            unset($this->subscriptions[\spl_object_hash($subscription)]);

            if (empty($this->subscriptions) && !$this->complete) {
                $this->waiting = new Future; // Wait for another subscriber.
            }
        };
    }

    /**
     * @return \Amp\Observer
     */
    public function getObserver() {
        $subscription = new Subscription($this->unsubscribe);
        $this->subscriptions[\spl_object_hash($subscription)] = $subscription;

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve();
        }

        return new Subscriber($subscription);
    }

    /**
     * {@inheritdoc}
     */
    protected function emit($value = null) {
        if ($this->complete) {
            if ($this->exception) {
                throw $this->exception;
            }

            throw new CompletedException("The observable has completed");
        }

        return new Coroutine($this->push($value));
    }

    /**
     * @coroutine
     *
     * @param mixed $value
     * @param bool $complete
     *
     * @return \Generator
     *
     * @throws \InvalidArgumentException
     * @throws \Throwable|\Exception
     */
    private function push($value, $complete = false) {
        if ($this->waiting !== null) {
            yield $this->waiting; // Wait for at least one observer to be registered.
        }

        $emitted = new Emitted($value, \count($this->subscriptions), $complete);

        foreach ($this->subscriptions as $subscription) {
            $subscription->push($emitted);
        }

        try {
            $value = (yield $emitted->wait());
        } catch (\Throwable $exception) {
            $this->complete = true;
            $this->exception = $exception;
            throw $exception;
        } catch (\Exception $exception) {
            $this->complete = true;
            $this->exception = $exception;
            throw $exception;
        }

        yield Coroutine::result($value);
    }

    /**
     * {@inheritdoc}
     */
    protected function complete($value = null) {
        if ($this->complete) {
            if ($this->exception) {
                throw $this->exception;
            }

            throw new CompletedException("The observable has completed");
        }

        $this->complete = true;

        return new Coroutine($this->push($value, true));
    }

    /**
     * {@inheritdoc}
     */
    protected function fail($exception) {
        if ($this->complete) {
            if ($this->exception) {
                throw $this->exception;
            }

            throw new CompletedException("The observable has completed");
        }

        $this->complete = true;

        return new Coroutine($this->push(new Failure($exception), true));
    }
}
