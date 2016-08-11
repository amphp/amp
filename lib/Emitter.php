<?php

namespace Amp;

final class Emitter implements Observable {
    use Internal\Producer;

    /**
     * @param callable(callable $emit): \Generator $emitter
     */
    public function __construct(callable $emitter) {
        $this->init();
    
        if (PHP_VERSION_ID >= 70100) {
            $emit = \Closure::fromCallable([$this, 'emit']);
        } else {
            $emit = function ($value) {
                return $this->emit($value);
            };
        }
        
        $result = $emitter($emit);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->when(function ($exception, $value) {
            if ($exception) {
                $this->fail($exception);
                return;
            }

            $this->resolve($value);
        });
    }
}
