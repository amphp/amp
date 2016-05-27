<?php

namespace Amp;

final class Observer {
    /**
     * @var \Amp\Internal\ObserverSubscriber
     */
    private $subscriber;

    /**
     * @param \Amp\Observable $observable
     */
    public function __construct(Observable $observable) {
        $this->subscriber = new Internal\ObserverSubscriber($observable);
    }

    /**
     * Disposes of the subscription.
     */
    public function __destruct() {
        $this->subscriber->dispose();
    }

    /**
     * Succeeds with true if a new value is available by calling getCurrent() or false if the observable has completed.
     * Calling getCurrent() will throw an exception if the observable completed. If an error occurs with the observable,
     * this coroutine will be rejected with the exception used to fail the observable.
     *
     * @return \Interop\Async\Awaitable
     *
     * @resolve bool
     *
     * @throws \Throwable|\Exception Exception used to fail the observable.
     */
    public function isValid() {
        return $this->subscriber->getAwaitable();
    }

    /**
     * Gets the last emitted value or throws an exception if the observable has completed.
     *
     * @return mixed Value emitted from observable.
     *
     * @throws \Amp\CompletedException If the observable has successfully completed.
     * @throws \LogicException If isValid() was not called before calling this method.
     */
    public function getCurrent() {
        return $this->subscriber->getCurrent();
    }

    /**
     * Gets the return value of the observable or throws the failure reason. Also throws an exception if the
     * observable has not completed.
     *
     * @return mixed Final return value of the observable.
     *
     * @throws \Amp\IncompleteException If the observable has not completed.
     */
    public function getReturn() {
        return $this->subscriber->getReturn();
    }
}
