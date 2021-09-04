<?php

namespace Amp;

use function Amp\Future\spawn;

/**
 * @template TValue
 * @template TSend
 * @template TReturn
 *
 * @template-implements Pipeline<TValue>
 * @template-implements \IteratorAggregate<int, TValue>
 */
final class AsyncGenerator implements Pipeline, \IteratorAggregate
{
    /** @var Internal\EmitSource<TValue, TSend> */
    private Internal\EmitSource $source;

    /** @var Future<TReturn> */
    private Future $future;

    /**
     * @param callable(mixed ...$args):\Generator $callable
     * @param mixed ...$args Arguments passed to callback.
     *
     * @throws \Error Thrown if the callable throws any exception.
     * @throws \TypeError Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $callable, mixed ...$args)
    {
        $this->source = $source = new Internal\EmitSource;

        try {
            $generator = $callable(...$args);

            if (!$generator instanceof \Generator) {
                throw new \TypeError("The callable did not return a Generator");
            }
        } catch (\Throwable $exception) {
            $this->source->error($exception);
            $this->future = Future::error($exception);
            return;
        }

        $this->future = spawn(static function () use ($generator, $source): mixed {
            try {
                $yielded = $generator->current();

                while ($generator->valid()) {
                    try {
                        $yielded = $generator->send($source->yield($yielded));
                    } catch (DisposedException $exception) {
                        throw $exception; // Destroys generator and fails pipeline.
                    } catch (\Throwable $exception) {
                        $yielded = $generator->throw($exception);
                    }
                }
            } catch (\Throwable $exception) {
                $source->error($exception);
                throw $exception;
            }

            $source->complete();
            return $generator->getReturn();
        });
    }

    public function __destruct()
    {
        $this->source->destroy();
    }

    /**
     * @inheritDoc
     *
     * @psalm-return TValue|null
     */
    public function continue(): mixed
    {
        return $this->source->continue();
    }

    /**
     * Sends a value to the async generator, resolving the back-pressure promise with the given value.
     * The first emitted value must be retrieved using {@see continue()}.
     *
     * @param mixed $value Value to send to the async generator.
     *
     * @psalm-param TSend $value
     *
     * @return mixed Returns null if the pipeline has completed.
     *
     * @psalm-return TValue
     *
     * @throws \Error If the first emitted value has not been retrieved using {@see continue()}.
     */
    public function send(mixed $value): mixed
    {
        return $this->source->send($value);
    }

    /**
     * Throws an exception into the async generator, failing the back-pressure promise with the given exception.
     * The first emitted value must be retrieved using {@see continue()}.
     *
     * @param \Throwable $exception Exception to throw into the async generator.
     *
     * @return mixed Returns null if the pipeline has completed.
     *
     * @psalm-return TValue
     *
     * @throws \Error If the first emitted value has not been retrieved using {@see continue()}.
     */
    public function throw(\Throwable $exception): mixed
    {
        return $this->source->throw($exception);
    }

    /**
     * Notifies the generator that the consumer is no longer interested in the generator output.
     *
     * @return void
     */
    public function dispose(): void
    {
        $this->source->dispose();
    }

    /**
     * @return TReturn
     */
    public function getReturn(): mixed
    {
        return $this->future->join();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        while (null !== $value = $this->source->continue()) {
            yield $value;
        }
    }
}
