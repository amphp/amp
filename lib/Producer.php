<?php

namespace Amp;

/**
 * @template-covariant TValue
 * @template-implements Iterator<TValue>
 *
 * @deprecated Use {@see AsyncGenerator} instead.
 *
 * @psalm-suppress DeprecatedInterface
 */
final class Producer implements Iterator
{
    private Internal\Producer $producer;

    /**
     * @param callable(callable(TValue):Promise):\Generator $producer
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $producer)
    {
        $this->producer = $emitter = new Internal\Producer;

        $result = $producer(\Closure::fromCallable([$this->producer, 'emit']));

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->onResolve(static function ($exception) use ($emitter): void {
            if ($emitter->isComplete()) {
                return;
            }

            if ($exception) {
                $emitter->fail($exception);
                return;
            }

            $emitter->complete();
        });
    }

    public function advance(): Promise
    {
        return $this->producer->advance();
    }

    public function getCurrent()
    {
        return $this->producer->getCurrent();
    }
}
