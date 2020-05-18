<?php

namespace Amp\Internal;

use Amp\AsyncGenerator;
use Amp\Promise;
use Amp\Stream;
use Amp\YieldedValue;

/**
 * Interface used internally by {@see AsyncGenerator} and {@see Yielder}.
 *
 * @internal
 *
 * @template-covariant TValue
 * @template TSend
 */
interface GeneratorStream extends Stream
{
    /**
     * Sends a value into the async generator.
     *
     * @param mixed $value
     *
     * @psalm-param TSend $value
     *
     * @return Promise<YieldedValue|null>
     *
     * @psalm-return Promise<YieldedValue<TValue>|null>
     */
    public function send($value): Promise;

    /**
     * Throws an exception into the async generator.
     *
     * @param \Throwable $exception
     *
     * @return Promise<YieldedValue|null>
     *
     * @psalm-return Promise<YieldedValue<TValue>|null>
     */
    public function throw(\Throwable $exception): Promise;
}
