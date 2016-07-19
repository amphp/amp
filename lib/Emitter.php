<?php

namespace Amp;

final class Emitter implements Observable {
    use Internal\Producer;

    /**
     * @param callable(callable $emit): \Generator $emitter
     */
    public function __construct(callable $emitter) {
        $this->init();
    
        /**
         * @param mixed $value
         *
         * @return \Interop\Async\Awaitable
         */
        $emit = function ($value = null) {
            return $this->emit($value);
        };
        
        $result = $emitter($emit);

        if (!$result instanceof \Generator) {
            throw new \LogicException("The callable did not return a Generator");
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
