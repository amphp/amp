<?php

declare(strict_types=1);

namespace Amp\Internal;

use Amp\{ Deferred, Observable, Subscriber, Success, function pipe };
use Interop\Async\{ Awaitable, Loop };

/**
 * Trait used by Observable implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Observable.
 * Note that it is the responsibility of the user of this trait to ensure that subscribers have a chance to subscribe first
 * before emitting values.
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
        $this->unsubscribe = function ($id) {
            $this->unsubscribe($id);
        };
    }

    /**
     * @param callable $onNext
     *
     * @return \Amp\Subscriber
     */
    public function subscribe(callable $onNext): Subscriber {
        if ($this->resolved) {
            return new Subscriber($this->nextId++, function() {});
        }

        $id = $this->nextId++;
        $this->subscribers[$id] = $onNext;

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

        if ($value instanceof Awaitable) {
            if ($value instanceof Observable) {
                $value->subscribe(function ($value) {
                    return $this->emit($value);
                });
                
                $value->when(function ($e) {
                    if ($e) {
                        $this->fail($e);
                    }
                });
                
                return $value; // Do not emit observable result.
            }
            
            $deferred = new Deferred;
            $value->when(function ($e, $v) use ($deferred) {
                if ($e) {
                    $this->fail($e);
                    $deferred->fail($e);
                    return;
                }
                
                $deferred->resolve($this->emit($v));
            });
            
            return $deferred->getAwaitable();
        }

        $awaitables = [];

        foreach ($this->subscribers as $onNext) {
            try {
                $result = $onNext($value);
                if ($result instanceof Awaitable) {
                    $awaitables[] = $result;
                }
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
        }

        if (!$awaitables) {
            return new Success($value);
        }

        $deferred = new Deferred;
        $count = \count($awaitables);
        $f = static function ($e) use ($deferred, $value, &$count) {
            if ($e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
            if (!--$count) {
                $deferred->resolve($value);
            }
        };

        foreach ($awaitables as $awaitable) {
            $awaitable->when($f);
        }

        return $deferred->getAwaitable();
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
        $this->unsubscribe = null;
    }
}
