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
    /** @var Internal\YieldSource<TValue, null> Has public yield, complete, and fail methods. */
    private $source;

    public function __construct()
    {
        $this->source = new Internal\YieldSource;
    }

    /**
     * Returns a Stream that can be given to an API consumer. This method may be called only once!
     *
     * @return Stream
     *
     * @psalm-return Stream<TValue>
     *
     * @throws \Error If this method is called more than once.
     */
    public function stream(): Stream
    {
        return $this->source->stream();
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
        return $this->source->yield($value);
    }

    /**
     * Completes the stream.
     *
     * @return void
     */
    public function complete()
    {
        $this->source->complete();
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
        $this->source->fail($reason);
    }
}
