<?php

namespace Amp;

final class AsyncGenerator implements Flow
{
    /** @var \Amp\Flow */
    private $flow;

    /**
     * @param callable(callable(mixed $value, mixed $key = null): Promise $yield): \Generator $callable
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $callable)
    {
        $generator = new class {
            use Internal\Generator {
                yield as public;
                complete as public;
                fail as public;
            }
        };

        if (\PHP_VERSION_ID < 70100) {
            $yield = static function ($value, $key = null) use ($generator): Promise {
                return $generator->yield($value, $key);
            };
        } else {
            $yield = \Closure::fromCallable([$generator, "yield"]);
        }

        $result = $callable($yield);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->onResolve(static function ($exception) use ($generator) {
            if ($exception) {
                $generator->fail($exception);
                return;
            }

            $generator->complete();
        });

        $this->flow = $generator->iterate();
    }

    /**
     * {@inheritdoc}
     */
    public function continue(): Promise
    {
        return $this->flow->continue();
    }
}
