<?php

namespace Amp\Internal;

use Amp\Promise;

/**
 * Wraps a GeneratorStream instance that has public methods to yield, complete, and fail into an object that only allows
 * access to the public API methods and sets $disposed to true when the object is destroyed.
 *
 * @internal
 *
 * @template TValue
 * @template TSend
 *
 * @template-implements GeneratorStream<TValue, TSend>
 */
class AutoDisposingGenerator extends AutoDisposingStream implements GeneratorStream
{
    /** @var GeneratorStream<TValue, TSend> */
    private $generator;

    public function __construct(GeneratorStream $generator)
    {
        parent::__construct($generator);
        $this->generator = $generator;
    }

    /**
     * @param mixed $value
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
     * @param \Throwable $exception
     *
     * @return Promise<array>
     */
    public function throw(\Throwable $exception): Promise
    {
        return $this->generator->throw($exception);
    }
}
