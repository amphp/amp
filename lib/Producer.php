<?php

namespace Amp;

use AsyncInterop\Loop;

final class Producer implements Stream {
    use Internal\CallableMaker, Internal\Producer;

    /**
     * @param callable(callable(mixed $value): Promise $emit): \Generator $producer
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $producer) {
        $result = $producer($this->callableFromInstanceMethod("emit"));

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
