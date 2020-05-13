<?php

namespace Amp;

/**
 * StreamSource is a container for a Stream that can yield values using the yield() method and completed using the
 * complete() and fail() methods. The contained Stream may be accessed using the stream() method. This object should
 * not be returned as part of a public API, but used internally to create and yield values to a Stream.
 *
 * @template TValue
 */
final class StreamSource
{
    /** @var Stream<TValue> Has public yield, complete, and fail methods. */
    private $stream;

    public function __construct()
    {
        $this->stream = new class implements Stream {
            use Internal\Yielder {
                createStream as public;
            }
        };
    }

    /**
     * Returns a Stream that can be given to an API consumer. This method may be called only once!
     *
     * @return Stream
     *
     * @psalm-return Stream<TValue>
     */
    public function stream(): Stream
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        return $this->stream->createStream();
    }

    /**
     * Yields a value to the stream.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<null> Resolves with null when the yielded value has been consumed or fails with
     *                       {@see DisposedException} if the stream has been destroyed.
     */
    public function yield($value): Promise
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        return $this->stream->yield($value);
    }

    /**
     * Completes the stream.
     *
     * @return void
     */
    public function complete()
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->stream->complete();
    }

    /**
     * Fails the stream with the given reason.
     *
     * @param \Throwable $reason
     *
     * @return void
     */
    public function fail(\Throwable $reason)
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->stream->fail($reason);
    }
}
