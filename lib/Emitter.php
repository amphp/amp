<?php

declare(strict_types=1);

namespace Amp;

use Interop\Async\{ Awaitable, Loop };

final class Emitter implements Observable {
    use Internal\Producer;

    /**
     * @param callable(callable(mixed $value): Awaitable $emit): \Generator $emitter
     */
    public function __construct(callable $emitter) {
        $this->init();
        
        // Defer first emit until next tick in order to give *all* subscribers a chance to subscribe first
        $pending = new Deferred;
        Loop::defer(static function () use (&$pending) {
            $temp = $pending;
            $pending = null;
            $temp->resolve();
        });
        
        $emit = function ($value) use (&$pending): Awaitable {
            if ($pending !== null) {
                return pipe($pending->getAwaitable(), function () use ($value): Awaitable {
                    return $this->emit($value);
                });
            }
            
            return $this->emit($value);
        };

        $result = $emitter($emit);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->when(function ($exception, $value): void {
            if ($exception) {
                $this->fail($exception);
                return;
            }

            $this->resolve($value);
        });
    }
}
