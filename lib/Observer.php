<?php

namespace Amp;

final class Observer {
    /**
     * @var \Amp\Disposable
     */
    private $subscriber;

    /**
     * @var \Amp\Deferred
     */
    private $deferred;

    /**
     * @var \Amp\Future
     */
    private $future;

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var mixed
     */
    private $current;

    /**
     * @var \Throwable|\Exception
     */
    private $exception;

    /**
     * @param \Amp\Observable $observable
     */
    public function __construct(Observable $observable) {
        $this->deferred = new Deferred;

        $deferred = &$this->deferred;
        $future   = &$this->future;
        $current  = &$this->current;

        $this->subscriber = $observable->subscribe(static function ($value) use (&$deferred, &$future, &$current) {
            $current = $value;

            $future = new Future;
            $deferred->resolve(true);

            return $future;
        });

        $complete = &$this->complete;
        $error    = &$this->exception;

        $this->subscriber->when(static function ($exception, $value) use (
            &$deferred, &$future, &$current, &$error, &$complete
        ) {
            $complete = true;

            if ($exception) {
                $current = null;
                $error = $exception;
                if ($future === null) {
                    $deferred->fail($exception);
                }
                return;
            }

            $current = $value;
            if ($future === null) {
                $deferred->resolve(false);
            }
        });
    }

    /**
     * Disposes of the subscriber.
     */
    public function __destruct() {
        $this->subscriber->dispose();

        if ($this->future !== null) {
            $this->future->resolve();
        }
    }

    /**
     * Succeeds with true if a new value is available by calling getCurrent() or false if the observable has completed.
     * Calling getCurrent() will throw an exception if the observable completed. If an error occurs with the observable,
     * the returned awaitable will fail with the exception used to fail the observable.
     *
     * @return \Interop\Async\Awaitable
     *
     * @resolve bool
     *
     * @throws \Throwable|\Exception Exception used to fail the observable.
     */
    public function isValid() {
        if ($this->complete) {
            if ($this->exception) {
                return new Failure($this->exception);
            }

            return new Success(false);
        }

        if ($this->future !== null) {
            $future = $this->future;
            $this->future = null;
            $this->deferred = new Deferred;
            $future->resolve();
        }

        return $this->deferred->getAwaitable();
    }

    /**
     * Gets the last emitted value or throws an exception if the observable has completed.
     *
     * @return mixed Value emitted from observable.
     *
     * @throws \LogicException If the observable has resolved or isValid() was not called before calling this method.
     */
    public function getCurrent() {
        if ($this->future === null) {
            throw new \LogicException("Awaitable returned from isValid() must resolve before calling this method");
        }

        if ($this->complete) {
            throw new \LogicException("The observable has completed");
        }

        return $this->current;
    }

    /**
     * Gets the return value of the observable or throws the failure reason. Also throws an exception if the
     * observable has not completed.
     *
     * @return mixed Final return value of the observable.
     *
     * @throws \LogicException If the observable has not completed.
     * @throws \Throwable|\Exception The exception used to fail the observable.
     */
    public function getReturn() {
        if (!$this->complete) {
            throw new \LogicException("The observable has not completed");
        }

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->current;
    }
}
