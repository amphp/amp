<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 */
final class LimitStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    /** @var int */
    private $limit;

    /** @var int */
    private $yielded = 0;

    public function __construct(Stream $stream, int $limit)
    {
        $this->stream = $stream;
        $this->limit = $limit;
    }

    public function continue(): Promise
    {
        return call(function () {
            $value = yield $this->stream->continue();

            if (++$this->yielded > $this->limit) {
                $this->stream->dispose();
            }

            return $value;
        });
    }

    public function dispose()
    {
        $this->stream->dispose();
    }
}
