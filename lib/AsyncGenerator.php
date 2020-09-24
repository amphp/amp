<?php

namespace Amp;

/**
 * @template TValue
 * @template TSend
 * @template TReturn
 */
final class AsyncGenerator implements Pipeline
{
    /** @var Internal\EmitSource<TValue, TSend> */
    private Internal\EmitSource $source;

    /** @var Coroutine<TReturn>|null */
    private Coroutine $coroutine;

    /**
     * @param callable(callable(TValue):Promise<TSend>):\Generator $callable
     *
     * @throws \Error Thrown if the callable throws any exception.
     * @throws \TypeError Thrown if the callable does not return a Generator.
     */
    public function __construct(callable $callable)
    {
        $this->source = $source = new Internal\EmitSource;

        if (\PHP_VERSION_ID < 70100) {
            $emit = static function ($value) use ($source): Promise {
                return $source->emit($value);
            };
        } else {
            $emit = \Closure::fromCallable([$source, "emit"]);
        }

        try {
            $generator = $callable($emit);
        } catch (\Throwable $exception) {
            throw new \Error("The callable threw an exception", 0, $exception);
        }

        if (!$generator instanceof \Generator) {
            throw new \TypeError("The callable did not return a Generator");
        }

        $this->coroutine = new Coroutine($generator);
        $this->coroutine->onResolve(static function ($exception) use ($source) {
            if ($source->isDisposed()) {
                return; // AsyncGenerator object was destroyed.
            }

            if ($exception) {
                $source->fail($exception);
                return;
            }

            $source->complete();
        });
    }

    public function __destruct()
    {
        $this->source->destroy();
    }

    /**
     * @inheritDoc
     */
    public function continue(): Promise
    {
        if ($this->coroutine === null) {
            $this->getReturn(); // Starts execution of the coroutine.
        }

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
     * @return Promise<mixed|null> Resolves with null if the pipeline has completed.
     *
     * @psalm-return Promise<TValue|null>
     *
     * @throws \Error If the first emitted value has not been retrieved using {@see continue()}.
     */
    public function send($value): Promise
    {
        return $this->source->send($value);
    }

    /**
     * Throws an exception into the async generator, failing the back-pressure promise with the given exception.
     * The first emitted value must be retrieved using {@see continue()}.
     *
     * @param \Throwable $exception Exception to throw into the async generator.
     *
     * @return Promise<mixed|null> Resolves with null if the pipeline has completed.
     *
     * @psalm-return Promise<TValue|null>
     *
     * @throws \Error If the first emitted value has not been retrieved using {@see continue()}.
     */
    public function throw(\Throwable $exception): Promise
    {
        return $this->source->throw($exception);
    }

    /**
     * Notifies the generator that the consumer is no longer interested in the generator output.
     *
     * @return void
     */
    public function dispose()
    {
        $this->source->dispose();
    }

    /**
     * @return Promise<mixed>
     *
     * @psalm-return Promise<TReturn>
     */
    public function getReturn(): Promise
    {
        return $this->coroutine;
    }
}
