<?php

namespace Amp;

/**
 * StreamSource is a container for a Stream that can emit values using the emit() method and completed using the
 * complete() and fail() methods. The contained Stream may be accessed using the stream() method. This object should
 * not be returned as part of a public API, but used internally to create and emit values to a Stream.
 *
 * @template TValue
 */
final class StreamSource
{
    /** @var Internal\EmitSource<TValue, null> Has public emit, complete, and fail methods. */
    private $source;

    public function __construct()
    {
        $this->source = new Internal\EmitSource;
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
     * Emits a value to the stream.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<null> Resolves with null when the emitted value has been consumed or fails with
     *                       {@see DisposedException} if the stream has been destroyed.
     */
    public function emit($value): Promise
    {
        return $this->source->emit($value);
    }

    /**
     * @return bool True if the stream has been completed or failed.
     */
    public function isComplete(): bool
    {
        return $this->source->isComplete();
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
