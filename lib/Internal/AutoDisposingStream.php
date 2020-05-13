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
class AutoDisposingStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
    }

    public function __destruct()
    {
        $this->stream->dispose();
    }

    /**
     * @return Promise<array>
     */
    public function continue(): Promise
    {
        return $this->stream->continue();
    }

    /**
     * @return void
     */
    public function dispose()
    {
        $this->stream->dispose();
    }
}
