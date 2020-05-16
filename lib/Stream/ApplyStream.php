<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;

/**
 * @template TValue
 * @template TApplied
 */
final class ApplyStream implements Stream
{
    /** @var Stream<TApplied> */
    private $stream;

    /**
     * @param Stream   $stream
     * @param callable(Stream<TValue>):Stream<TApplied> $operator
     */
    public function __construct(Stream $stream, callable $operator)
    {
        $stream = $operator($stream);

        if (!$stream instanceof Stream) {
            throw new \TypeError('$operator callback must return an instance of ' . Stream::class);
        }

        $this->stream = $stream;
    }

    /**
     * @psalm-return Promise<list<TApplied>|null>
     */
    public function continue(): Promise
    {
        return $this->stream->continue();
    }

    public function dispose()
    {
        $this->stream->dispose();
    }
}
