<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 * @template TMap
 */
final class MapStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    /** @var callable(TValue):Promise<TMap> */
    private $mapper;

    public function __construct(Stream $stream, callable $mapper)
    {
        $this->stream = $stream;
        $this->mapper = $mapper;
    }

    public function continue(): Promise
    {
        return call(function () {
            if (list($value) = yield $this->stream->continue()) {
                return yield call($this->mapper, $value);
            }

            return null;
        });
    }

    public function dispose()
    {
        $this->stream->dispose();
    }
}
