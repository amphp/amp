<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Loop;

final class Emitter implements Observable {
    use CallableMaker, Internal\Producer;

    /**
     * @param callable(callable(mixed $value): Promise $emit): \Generator $emitter
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $emitter) {
        $result = $emitter($this->callableFromInstanceMethod("emit"));

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
