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
class DisposableStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    /** @var callable */
    private $dispose;

    public function __construct(Stream $stream, callable $dispose)
    {
        $this->stream = $stream;
        $this->dispose = $dispose;
    }

    public function __destruct()
    {
        ($this->dispose)();
    }

    /**
     * @return Promise<array>
     */
    public function continue(): Promise
    {
        return $this->stream->continue();
    }
}
