<?php

namespace Amp\Stream;

use Amp\Promise;
use Amp\Stream;
use function Amp\call;

/**
 * @template TValue
 */
final class ToArrayOperator
{
    /** @var Promise<list<TValue>> */
    private $promise;

    /**
     * @param Stream<TValue> $stream
     */
    public function __construct(Stream $stream)
    {
        $this->promise = call(function () use ($stream) {
            /** @psalm-var list $array */
            $array = [];

            while (list($value) = yield $stream->continue()) {
                $array[] = $value;
            }

            return $array;
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
