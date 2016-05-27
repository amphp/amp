<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Coroutine;
use Amp\Failure;
use Amp\Subscriber;

trait Producer {
    /**
     * @var Subscription[]
     */
    private $subscriptions = [];

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

    /**
     * 
     */
    public function subscribe(callable $onNext) {
        if ($this->unsubscribe === null) {
            $this->unsubscribe = function (Subscription $subscription) {
                unset($this->subscriptions[\spl_object_hash($subscription)]);
            };
        }

        $subscription = new Subscription($this->unsubscribe);
        $this->subscriptions[\spl_object_hash($subscription)] = $subscription;

        return new Subscriber($onNext, $subscription);
    }

    /**
     * Emits a value from the observable. If the value is an awaitable, the success value will be emitted. If the
     * awaitable fails, the observable will fail with the same exception. The returned awaitable is resolved with the
     * emitted value once all subscribers have been invoked.
     *
     * @param mixed $value
     *
     * @return \Interop\Async\Awaitable
     */
    protected function emit($value = null) {
        if ($this->complete) {
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
        $emitted = new Emitted($value, \count($this->subscriptions), $complete);

        foreach ($this->subscriptions as $subscription) {
            $subscription->push($emitted);
        }

        try {
            $value = (yield $emitted->wait());
        } catch (\Throwable $exception) {
            $this->complete = true;
            throw $exception;
        } catch (\Exception $exception) {
            $this->complete = true;
            throw $exception;
        }

        yield Coroutine::result($value);
    }

    /**
     * Completes the observable with the given value. If the value is an awaitable, the success value will be emitted.
     * If the awaitable fails, the observable will fail with the same exception. The returned awaitable is resolved
     * with the completion value once all subscribers have received all prior emitted values.
     *
     * @param mixed $value
     *
     * @return \Interop\Async\Awaitable
     */
    protected function complete($value = null) {
        if ($this->complete) {
            throw new CompletedException("The observable has completed");
        }

        $this->complete = true;

        return new Coroutine($this->push($value, true));
    }

    /**
     * Fails the observable with the given exception. The returned awaitable fails with the given exception once all
     * subscribers have been received all prior emitted values.
     *
     * @param \Throwable|\Exception $exception
     *
     * @return \Interop\Async\Awaitable
     */
    protected function fail($exception) {
        if ($this->complete) {
            throw new CompletedException("The observable has completed");
        }

        $this->complete = true;

        return new Coroutine($this->push(new Failure($exception), true));
    }
}
