<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Coroutine;
use Amp\DisposedException;
use Amp\Future;
use Amp\Observable;
use Interop\Async\Awaitable;

class EmitQueue {
    /**
     * @var bool
     */
    private $busy = false;

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var \Throwable|\Exception|null
     */
    private $reason;

    /**
     * @var \Amp\Future
     */
    private $future;

    /**
     * @var \Amp\Internal\Emitted
     */
    private $emitted;

    /**
     * @var int Number of listening iterators.
     */
    private $listeners = 0;

    public function __construct() {
        $this->future = new Future;
        $this->emitted = new Emitted($this->future);
    }

    /**
     * @param callable $emitter
     */
    public function start(callable $emitter) {
        /**
         * Emits a value from the observable.
         *
         * @param mixed $value If $value is an instance of \Interop\Async\Awaitable, the success value is used as the
         *     value to emit or the failure reason is used to fail the awaitable returned from this function.
         *
         * @return \Interop\Async\Awaitable
         *
         * @resolve mixed The emitted value (the resolution value of $value)
         *
         * @throws \Amp\CompletedException If the observable has been completed.
         * @throws \Amp\DisposedException If the observable has been disposed.
         */
        $emit = function ($value = null) {
            return new Coroutine($this->push($value));
        };

        try {
            $generator = $emitter($emit);

            if (!$generator instanceof \Generator) {
                throw new \LogicException("Callable must be a coroutine");
            }

            $coroutine = new Coroutine($generator);
            $coroutine->when([$this, 'done']);
        } catch (\Throwable $exception) {
            $this->done($exception);
        } catch (\Exception $exception) {
            $this->done($exception);
        }
    }

    /**
     * @coroutine
     *
     * @param mixed $value
     *
     * @return \Generator
     *
     * @throws \InvalidArgumentException
     * @throws \Throwable|\Exception
     */
    public function push($value) {
        if ($this->complete) {
            throw $this->reason ?: new CompletedException("The observable has completed");
        }

        if ($this->busy) {
            throw new \LogicException("Cannot emit values simultaneously");
        }

        $this->busy = true;

        try {
            if ($value instanceof Observable) {
                $iterator = $value->getIterator();

                while (yield $iterator->isValid()) {
                    yield $this->emit($iterator->getCurrent());
                }

                yield Coroutine::result($iterator->getReturn());
                return;
            }

            if ($value instanceof Awaitable) {
                $value = (yield $value);
            }

            yield $this->emit($value);
        } catch (\Throwable $exception) {
            $this->done($exception);
            throw $exception;
        } catch (\Exception $exception) {
            $this->done($exception);
            throw $exception;
        } finally {
            $this->busy = false;
        }

        yield Coroutine::result($value);
    }

    /**
     * @param mixed $value Value to emit.
     *
     * @return \Interop\Async\Awaitable
     */
    private function emit($value) {
        $future = $this->future;
        $emitted = $this->emitted;

        $this->future = new Future;
        $this->emitted = new Emitted($this->future);

        $future->resolve($value);

        return $emitted->wait();
    }

    /**
     * Increments the number of listening iterators.
     */
    public function increment() {
        ++$this->listeners;
    }

    /**
     * Decrements the number of listening iterators. Marks the queue as disposed if the count reaches 0.
     */
    public function decrement() {
        if (--$this->listeners <= 0 && !$this->complete) {
            $this->dispose(new DisposedException("The observable was automatically disposed"));
        }
    }

    /**
     * @return \Amp\Internal\Emitted
     */
    public function pull() {
        return $this->emitted;
    }

    /**
     * Marks the observable as complete, failing with the given exception or completing with the given value.
     *
     * @param \Throwable|\Exception|null $exception
     * @param mixed $value
     */
    public function done($exception, $value = null) {
        if ($this->complete) {
            return;
        }

        $this->complete = true;

        if ($exception) {
            $this->reason = $exception;
            $this->future->fail($exception);
            return;
        }

        $this->future->resolve($value);
    }

    /**
     * Disposes the observable.
     *
     * @param \Exception|null $exception
     */
    public function dispose(\Exception $exception = null) {
        $this->done($exception ?: new DisposedException("The observable was disposed"));
    }

    /**
     * @return bool
     */
    public function isComplete() {
        return $this->complete;
    }

    /**
     * @return bool
     */
    public function isFailed() {
        return $this->reason !== null;
    }

    /**
     * @return \Exception|\Throwable
     */
    public function getReason() {
        if ($this->reason === null) {
            throw new \LogicException("The observable has not failed");
        }

        return $this->reason;
    }
}
