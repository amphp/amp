<?php

namespace Amp;

final class Producer implements Iterator {
    use Internal\Producer {
        advance as public;
        getCurrent as public;
    }

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

        $coroutine = new Coroutine($result);
        $coroutine->onResolve(function ($exception) {
            if ($this->complete) {
                return;
            }

            if ($exception) {
                $this->fail($exception);
                return;
            }

            $this->complete();
        });
    }
}
