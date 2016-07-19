<?php

namespace Amp\Internal;

use Amp\Coroutine;
use Amp\Future;
use Amp\Observable;
use Amp\Subscriber;
use Amp\Success;
use Amp\UnsubscribedException;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * Trait used by Observable implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Observable.
 *
 * @internal
 */
trait Producer {
    use Placeholder {
        resolve as complete;
    }

    /**
     * @var callable[]
     */
    private $subscribers = [];

    /**
     * @var \Amp\Future|null
     */
    private $waiting;

    /**
     * @var \Amp\Future[]
     */
    private $futures = [];

    /**
     * @var string
     */
    private $nextId = "a";

    /**
     * @var callable
     */
    private $unsubscribe;

    /**
     * Initializes the trait. Use as constructor or call within using class constructor.
     */
    public function init()
    {
        $this->waiting = new Future;
        $this->unsubscribe = function ($id, $exception = null) {
            $this->unsubscribe($id, $exception);
        };
    }

    /**
     * @param callable $onNext
     *
     * @return \Amp\Subscriber
     */
    public function subscribe(callable $onNext) {
        if ($this->result !== null) {
            return new Subscriber(
                $this->nextId++,
                $this->unsubscribe
            );
        }

        $id = $this->nextId++;
        $this->subscribers[$id] = $onNext;

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve();
        }

        return new Subscriber($id, $this->unsubscribe);
    }

    /**
     * @param string $id
     * @param \Throwable|\Exception|null $exception
     */
    private function unsubscribe($id, $exception = null) {
        if (!isset($this->subscribers[$id])) {
            return;
        }

        unset($this->subscribers[$id]);

        if (empty($this->subscribers)) {
            $this->waiting = new Future;
        }
    }

    /**
     * Emits a value from the observable. The returned awaitable is resolved with the emitted value once all subscribers
     * have been invoked.
     *
     * @param mixed $value
     *
     * @return \Interop\Async\Awaitable
     *
     * @throws \LogicException If the observable has resolved.
     */
    private function emit($value = null) {
        if ($this->resolved) {
            throw new \LogicException("The observable has been resolved; cannot emit more values");
        }

        return new Coroutine($this->push($value));
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
    private function push($value) {
        while ($this->waiting !== null) {
            yield $this->waiting;
        }

        try {
            if ($value instanceof Observable) {
                $subscriber = $value->subscribe(function ($value) {
                    return $this->emit($value);
                });
                yield Coroutine::result(yield $subscriber);
                return;
            }

            if ($value instanceof Awaitable) {
                $value = (yield $value);
            }
        } catch (\Throwable $exception) {
            if (!$this->resolved) {
                $this->fail($exception);
            }
            throw $exception;
        } catch (\Exception $exception) {
            if (!$this->resolved) {
                $this->fail($exception);
            }
            throw $exception;
        }

        $awaitables = [];

        foreach ($this->subscribers as $id => $onNext) {
            try {
                $result = $onNext($value);
                if ($result instanceof Awaitable) {
                    $awaitables[$id] = $result;
                }
            } catch (\Throwable $exception) {
                $this->unsubscribe($id, $exception);
            } catch (\Exception $exception) {
                $this->unsubscribe($id, $exception);
            }
        }

        foreach ($awaitables as $id => $awaitable) {
            try {
                yield $awaitable;
            } catch (\Throwable $exception) {
                $this->unsubscribe($id, $exception);
            } catch (\Exception $exception) {
                $this->unsubscribe($id, $exception);
            }
        }

        yield Coroutine::result($value);
    }

    /**
     * Resolves the observable with the given value.
     *
     * @param mixed $value
     *
     * @throws \LogicException If the observable has already been resolved.
     */
    private function resolve($value = null) {
        $this->subscribers = [];

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve();
        }

        $this->complete($value);
    }
}
