<?php

namespace Amp;

/**
 * @deprecated Use {@see await()} and ext-fiber to await promises.
 *
 * Creates a promise from a generator function yielding promises.
 *
 * When a promise is yielded, execution of the generator is interrupted until the promise is resolved. A success
 * value is sent into the generator, while a failure reason is thrown into the generator. Using a coroutine,
 * asynchronous code can be written without callbacks and be structured like synchronous code.
 *
 * @template-covariant TReturn
 * @template-implements Promise<TReturn>
 */
final class Coroutine implements Promise
{
    private Promise $promise;

    /**
     * @param \Generator $generator
     * @psalm-param \Generator<mixed,Promise|ReactPromise|array<array-key,
     *     Promise|ReactPromise>,mixed,Promise<TReturn>|ReactPromise|TReturn> $generator
     */
    public function __construct(\Generator $generator)
    {
        $this->promise = async(function () use ($generator): mixed {
            $yielded = $generator->current();

            while ($generator->valid()) {
                try {
                    $value = await($yielded);
                } catch (\Throwable $exception) {
                    $yielded = $generator->throw($exception);
                    continue;
                }

                $yielded = $generator->send($value);
            }

            return $generator->getReturn();
        });
    }

    /** @inheritDoc */
    public function onResolve(callable $onResolved): void
    {
        $this->promise->onResolve($onResolved);
    }
}
