<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Asynchronous iterator that can be used within a coroutine to iterate over the emitted values from an Observable.
 *
 * Example:
 * $observer = new Observer($observable); // $observable is an instance of \Amp\Observable
 * while (yield $observer->next()) {
 *     $emitted = $observer->getCurrent();
 * }
 * $result = $observer->getResult();
 */
class Observer {
    /** @var mixed[] */
    private $values = [];

    /** @var \Amp\Deferred[] */
    private $deferreds = [];

    /** @var int */
    private $position = -1;

    /** @var \Amp\Deferred|null */
    private $deferred;

    /** @var bool */
    private $resolved = false;

    /** @var mixed */
    private $result;

    /** @var \Throwable|null */
    private $exception;

    /**
     * @param \Amp\Observable $observable
     */
    public function __construct(Observable $observable) {
        $deferred  = &$this->deferred;
        $values    = &$this->values;
        $deferreds = &$this->deferreds;
        $resolved  = &$this->resolved;

        $observable->subscribe(static function ($value) use (&$deferred, &$values, &$deferreds, &$resolved) {
            $values[] = $value;
            $deferreds[] = $pressure = new Deferred;

            if ($deferred !== null) {
                $temp = $deferred;
                $deferred = null;
                $temp->resolve(true);
            }

            if ($resolved) {
                return null;
            }

            return $pressure->getAwaitable();
        });

        $result = &$this->result;
        $error  = &$this->exception;

        $observable->when(static function ($exception, $value) use (&$deferred, &$result, &$error, &$resolved) {
            $resolved = true;

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
     * Marks the observer as resolved to relieve back-pressure on the observable.
     */
    public function __destruct() {
        $this->resolved = true;
        
        foreach ($this->deferreds as $deferred) {
            $deferred->resolve();
        }
    }

    /**
     * Succeeds with true if an emitted value is available by calling getCurrent() or false if the observable has
     * resolved. If the observable fails, the returned awaitable will fail with the same exception.
     *
     * @return \Interop\Async\Awaitable<bool>
     */
    public function next(): Awaitable {
        if (isset($this->deferreds[$this->position])) {
            $future = $this->deferreds[$this->position];
            unset($this->values[$this->position], $this->deferreds[$this->position]);
            $future->resolve();
        }

        ++$this->position;

        if (isset($this->values[$this->position])) {
            return new Success(true);
        }

        if ($this->resolved) {
            --$this->position;

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
     * @throws \Error If the observable has resolved or next() was not called before calling this method.
     */
    public function getCurrent() {
        if (empty($this->values) && $this->resolved) {
            throw new \Error("The observable has resolved");
        }

        if (!isset($this->values[$this->position])) {
            throw new \Error("Awaitable returned from next() must resolve before calling this method");
        }

        return $this->values[$this->position];
    }

    /**
     * Gets the result of the observable or throws the failure reason. Also throws an exception if the observable has
     * not completed.
     *
     * @return mixed Final return value of the observable.
     *
     * @throws \Error If the observable has not completed.
     * @throws \Throwable The exception used to fail the observable.
     */
    public function getResult() {
        if (!$this->resolved) {
            throw new \Error("The observable has not resolved");
        }

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->result;
    }
    
    /**
     * Returns an array of values that were not consumed by the Observer before the Observable completed.
     *
     * @return array Unconsumed emitted values.
     *
     * @throws \Error If the observable has not completed.
     */
    public function drain(): array {
        if (!$this->resolved) {
            throw new \Error("The observable has not resolved");
        }
    
        $values = $this->values;
        $this->values = [];
        $this->position = -1;
        
        $deferreds = $this->deferreds;
        $this->deferreds = [];
        foreach ($deferreds as $deferred) {
            $deferred->resolve();
        }
    
        return $values;
    }
}
