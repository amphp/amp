<?php

declare(strict_types=1);

namespace Amp;

use Interop\Async\{ Awaitable, Loop };

final class Emitter implements Observable {
    use Internal\Producer;

    /**
     * @param callable(callable(mixed $value): Awaitable $emit): \Generator $emitter
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $emitter) {
        $this->init();
        
        if (PHP_VERSION_ID >= 70100) {
            $emit = \Closure::fromCallable([$this, 'emit']);
        } else {
            $emit = function ($value): Awaitable {
                return $this->emit($value);
            };
        }

        $result = $emitter($emit);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }
        
        Loop::defer(function () use ($result) {
            $coroutine = new Coroutine($result);
            $coroutine->when(function ($exception, $value) {
                if ($this->resolved) {
                    return;
                }
        
                if ($exception) {
                    $this->fail($exception);
                    return;
                }
        
                $this->resolve($value);
            });
        });
    }
}
