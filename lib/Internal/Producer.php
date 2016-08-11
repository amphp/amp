<?php

namespace Amp\Internal;

use Amp\Coroutine;
use Amp\Future;
use Amp\Observable;
use Amp\Subscriber;
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
    private function init() {
        $this->waiting = new Future;

        if (PHP_VERSION_ID >= 70100) {
            $this->unsubscribe = \Closure::fromCallable([$this, 'unsubscribe']);
        } else {
            $this->unsubscribe = function ($id) {
                $this->unsubscribe($id);
            };
        }
    }

    /**
     * @param callable $onNext
     *
     * @return \Amp\Subscriber
     */
    public function subscribe(callable $onNext): Subscriber {
        if ($this->resolved) {
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
     */
    private function unsubscribe(string $id) {
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
     * @throws \Error If the observable has resolved.
     */
    private function emit($value): Awaitable {
        if ($this->resolved) {
            throw new \Error("The observable has been resolved; cannot emit more values");
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
     * @throws \Error
     * @throws \Throwable
     */
    private function push($value): \Generator {
        if ($this->waiting !== null) {
            yield $this->waiting;
        }

        try {
            if ($value instanceof Observable) {
                $value->subscribe(function ($value) {
                    return $this->emit($value);
                });
                return yield $value;
            }

            if ($value instanceof Awaitable) {
                $value = yield $value;
            }
        } catch (\Throwable $exception) {
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
                Loop::defer(static function () use ($exception) {
                    throw $exception;
                });
            }
        }

        foreach ($awaitables as $id => $awaitable) {
            try {
                yield $awaitable;
            } catch (\Throwable $exception) {
                Loop::defer(static function () use ($exception) {
                    throw $exception;
                });
            }
        }

        return $value;
    }

    /**
     * Resolves the observable with the given value.
     *
     * @param mixed $value
     *
     * @throws \Error If the observable has already been resolved.
     */
    private function resolve($value = null) {
        $this->complete($value);

        $this->subscribers = [];

        if ($this->waiting !== null) {
            $this->waiting->resolve();
            $this->waiting = null;
        }
    }
}
