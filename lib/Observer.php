<?php

namespace Amp;

final class Observer {
    /**
     * @var \Amp\Disposable
     */
    private $subscriber;

    /**
     * @var mixed[]
     */
    private $values = [];

    /**
     * @var \Amp\Future[]
     */
    private $futures = [];

    /**
     * @var int
     */
    private $position = -1;

    /**
     * @var \Amp\Deferred|null
     */
    private $deferred;

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var mixed
     */
    private $result;

    /**
     * @var \Throwable|\Exception|null
     */
    private $exception;

    /**
     * @param \Amp\Observable $observable
     */
    public function __construct(Observable $observable) {
        $deferred = &$this->deferred;
        $values   = &$this->values;
        $futures  = &$this->futures;

        $this->subscriber = $observable->subscribe(static function ($value) use (&$deferred, &$values, &$futures) {
            $values[] = $value;
            $futures[] = $future = new Future;

            if ($deferred !== null) {
                $temp = $deferred;
                $deferred = null;
                $temp->resolve($value);
            }

            return $future;
        });

        $complete = &$this->complete;
        $result   = &$this->result;
        $error    = &$this->exception;

        $this->subscriber->when(static function ($exception, $value) use (&$deferred, &$result, &$error, &$complete) {
            $complete = true;

            if ($exception) {
                $result = null;
                $error = $exception;
                if ($deferred !== null) {
                    $deferred->fail($exception);
                }
                return;
            }

            $result = $value;
            if ($deferred !== null) {
                $deferred->resolve(false);
            }
        });
    }

    /**
     * Disposes of the subscriber.
     */
    public function __destruct() {
        if (!$this->complete) {
            $this->subscriber->dispose();
        }

        foreach ($this->futures as $future) {
            $future->resolve();
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
        if (isset($this->futures[$this->position])) {
            $future = $this->futures[$this->position];
            unset($this->values[$this->position], $this->futures[$this->position]);
            $future->resolve();
        }

        ++$this->position;

        if (isset($this->values[$this->position])) {
            return new Success(true);
        }

        if ($this->complete) {
            if ($this->exception) {
                return new Failure($this->exception);
            }

            return new Success(false);
        }

        $this->deferred = new Deferred;
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
        if (empty($this->values) && $this->complete) {
            throw new \LogicException("The observable has completed");
        }

        if (!isset($this->values[$this->position])) {
            throw new \LogicException("Awaitable returned from isValid() must resolve before calling this method");
        }

        return $this->values[$this->position];
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

        return $this->result;
    }
}
