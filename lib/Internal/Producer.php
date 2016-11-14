<?php declare(strict_types = 1);

namespace Amp\Internal;

use Amp\{ Deferred, Success };
use Interop\Async\{ Loop, Promise };

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

    /** @var callable[] */
    private $subscribers = [];
    
    /**
     * @param callable $onNext
     */
    public function subscribe(callable $onNext) {
        if ($this->resolved) {
            return;
        }

        $this->subscribers[] = $onNext;
    }

    /**
     * Emits a value from the observable. The returned promise is resolved with the emitted value once all subscribers
     * have been invoked.
     *
     * @param mixed $value
     *
     * @return \Interop\Async\Promise
     *
     * @throws \Error If the observable has resolved.
     */
    private function emit($value): Promise {
        if ($this->resolved) {
            throw new \Error("The observable has been resolved; cannot emit more values");
        }

        if ($value instanceof Promise) {
            $deferred = new Deferred;
            $value->when(function ($e, $v) use ($deferred) {
                if ($this->resolved) {
                    $deferred->fail(
                        new \Error("The observable was resolved before the promise result could be emitted")
                    );
                    return;
                }
                
                if ($e) {
                    $this->fail($e);
                    $deferred->fail($e);
                    return;
                }
                
                $deferred->resolve($this->emit($v));
            });
            
            return $deferred->promise();
        }

        $promises = [];

        foreach ($this->subscribers as $onNext) {
            try {
                $result = $onNext($value);
                if ($result instanceof Promise) {
                    $promises[] = $result;
                }
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
        }

        if (!$promises) {
            return new Success($value);
        }

        $deferred = new Deferred;
        $count = \count($promises);
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

        foreach ($promises as $promise) {
            $promise->when($f);
        }

        return $deferred->promise();
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
    }
}
