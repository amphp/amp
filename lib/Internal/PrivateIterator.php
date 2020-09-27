<?php

namespace Amp\Internal;

use Amp\Iterator;
use Amp\Promise;

/**
 * Wraps a Producer instance that has public methods to emit, complete, and fail into an object that only allows
 * access to the public API methods.
 *
 * @template-covariant TValue
 * @template-implements Iterator<TValue>
 */
final class PrivateIterator implements Iterator
{
    /** @var Producer<TValue> */
    private Producer $producer;

    /**
     * @param Producer $producer
     *
     * @psalm-param Iterator<TValue> $iterator
     */
    public function __construct(Producer $producer)
    {
        $this->producer = $producer;
    }

    /**
     * @return Promise<bool>
     */
    public function advance(): Promise
    {
        return $this->producer->advance();
    }

    /**
     * @psalm-return TValue
     */
    public function getCurrent()
    {
        return $this->producer->getCurrent();
    }
}
