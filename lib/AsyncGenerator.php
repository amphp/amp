<?php

namespace Amp;

/**
 * @template TValue
 * @template TSend
 * @template TReturn
 */
final class AsyncGenerator implements Stream
{
    /** @var Internal\GeneratorStream */
    private $generator;

    /** @var Promise<TReturn> */
    private $coroutine;

    /**
     * @param callable(callable(TValue):Promise<TSend>):\Generator $callable
     *
     * @throws \Error Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $callable)
    {
        $source = new class implements Internal\GeneratorStream {
            use Internal\Yielder {
                createGenerator as public;
            }
        };

        if (\PHP_VERSION_ID < 70100) {
            $yield = static function ($value) use ($source): Promise {
                return $source->yield($value);
            };
        } else {
            $yield = \Closure::fromCallable([$source, "yield"]);
        }

        $result = $callable($yield);

        if (!$result instanceof \Generator) {
            throw new \TypeError("The callable did not return a Generator");
        }

        $this->coroutine = new Coroutine($result);
        $this->coroutine->onResolve(static function ($exception) use ($source) {
            if ($exception) {
                $source->fail($exception);
                return;
            }

            $source->complete();
        });

        $this->generator = $source->createGenerator();
    }

    /**
     * @return TransformationStream<TValue>
     */
    public function transform(): TransformationStream
    {
        return new TransformationStream($this);
    }

    /**
     * Continues the async generator, resolving the back-pressure promise with null.
     *
     * @return Promise<array>
     */
    public function continue(): Promise
    {
        return $this->generator->continue();
    }

    /**
     * Sends a value to the async generator, resolving the back-pressure promise with the given value.
     *
     * @param mixed $value Value to send to the async generator.
     *
     * @psalm-param TSend $value
     *
     * @return Promise<array>
     */
    public function send($value): Promise
    {
        return $this->generator->send($value);
    }

    /**
     * Throws an exception into the async generator, failing the back-pressure promise with the given exception.
     *
     * @param \Throwable $exception Exception to throw into the async generator.
     *
     * @return Promise<array>
     */
    public function throw(\Throwable $exception): Promise
    {
        return $this->generator->throw($exception);
    }

    /**
     * Notifies the generator that the consumer is no longer interested in the generator output.
     *
     * @return void
     */
    public function dispose()
    {
        $this->generator->dispose();
    }

    /**
     * @return Promise<TReturn>
     */
    public function getReturn(): Promise
    {
        return $this->coroutine;
    }

}
