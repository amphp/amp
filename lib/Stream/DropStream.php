<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 */
final class DropStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    /** @var int */
    private $count;

    /** @var int */
    private $dropped = 0;

    public function __construct(Stream $stream, int $count)
    {
        $this->stream = $stream;
        $this->count = $count;
    }

    public function continue(): Promise
    {
        return call(function () {
            while (++$this->dropped <= $this->count) {
                if (yield $this->stream->continue() === null) {
                    return null;
                }
            }

            return yield $this->stream->continue();
        });
    }

    public function dispose()
    {
        $this->stream->dispose();
    }
}
