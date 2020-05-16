<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 */
final class FilterStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    /** @var callable(TValue):Promise<bool> */
    private $filter;

    public function __construct(Stream $stream, callable $filter)
    {
        $this->stream = $stream;
        $this->filter = $filter;
    }

    public function continue(): Promise
    {
        return call(function () {
            while (list($value) = yield $this->stream->continue()) {
                if (!yield call($this->filter, $value)) {
                    return $value;
                }
            }

            return null;
        });
    }

    public function dispose()
    {
        $this->stream->dispose();
    }
}
