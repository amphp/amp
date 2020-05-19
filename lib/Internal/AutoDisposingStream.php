<?php

namespace Amp\Internal;

use Amp\Promise;
use Amp\Stream;

/**
 * Wraps a Stream instance that has public methods to yield, complete, and fail into an object that only allows
 * access to the public API methods and sets $disposed to true when the object is destroyed.
 *
 * @internal
 *
 * @template-covariant TValue
 * @template-implements Stream<TValue>
 */
final class AutoDisposingStream implements Stream
{
    /** @var YieldSource<TValue, null> */
    private $source;

    public function __construct(YieldSource $source)
    {
        $this->source = $source;
    }

    public function __destruct()
    {
        $this->source->dispose();
    }

    /**
     * @inheritDoc
     */
    public function continue(): Promise
    {
        return $this->source->continue();
    }

    /**
     * @inheritDoc
     */
    public function dispose()
    {
        $this->source->dispose();
    }
}
