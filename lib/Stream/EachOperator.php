<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 */
final class EachOperator
{
    /** @var Promise<list<TValue>> */
    private $promise;

    /**
     * @param Stream<TValue> $stream
     * @param callable(TValue):void $each
     */
    public function __construct(Stream $stream, callable $each)
    {
        $this->promise = call(function () use ($stream, $each) {
            while (list($value, $key) = yield $stream->continue()) {
                yield call($each, $value, $key);
            }
        });
    }

    /**
     * @return Promise<list<TValue>>
     */
    public function promise(): Promise
    {
        return $this->promise;
    }
}
