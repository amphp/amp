<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Deferred;
use Amp\Future;
use Amp\IncompleteException;
use Amp\Observable;
use Amp\Success;

final class ObserverSubscriber {
    /**
     * @var \Amp\Disposable
     */
    private $disposable;

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
     * @param \Amp\Observable $observable
     */
    public function __construct(Observable $observable) {
        $this->deferred = new Deferred;

        $this->disposable = $observable->subscribe([$this, 'onNext']);
        $this->disposable->when([$this, 'onComplete']);
    }

    /**
     * @param mixed $value
     *
     * @return \Amp\Future
     */
    public function onNext($value) {
        $this->current = $value;

        $this->future = new Future;
        $this->deferred->resolve(true);

        return $this->future;
    }

    /**
     * @param \Throwable|\Exception|null $exception
     * @param mixed $value
     */
    public function onComplete($exception, $value) {
        $this->complete = true;

        if ($exception) {
            $this->current = null;
            $this->deferred->fail($exception);
            return;
        }

        $this->current = $value;
        $this->deferred->resolve(false);
    }

    /**
     * @return \Interop\Async\Awaitable
     */
    public function getAwaitable() {
        if ($this->complete) {
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
     * @return mixed
     *
     * @throws \Amp\CompletedException
     */
    public function getCurrent() {
        if ($this->future === null) {
            throw new \LogicException("Awaitable returned from isValid() must resolve before calling this method");
        }

        if ($this->complete) {
            throw new CompletedException("The observable has completed");
        }

        return $this->current;
    }

    /**
     * @return mixed
     *
     * @throws \Amp\IncompleteException
     */
    public function getReturn() {
        if (!$this->complete) {
            throw new IncompleteException("The observable has not completed");
        }

        return $this->current;
    }
    
    public function dispose() {
        $this->disposable->dispose();
    }
}
