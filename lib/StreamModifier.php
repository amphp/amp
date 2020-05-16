<?php

namespace Amp;

use Amp\Stream\EachOperator;

/**
 * @template TValue
 */
final class StreamModifier
{
    /** @var Stream<TValue> */
    private $stream;

    /**
     * @param Stream<TValue> $stream
     */
    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
    }

    /**
     * @return Stream<TValue>
     */
    public function stream(): Stream
    {
        return $this->stream;
    }

    public function apply(callable $operator): self
    {
        $clone = clone $this;
        $clone->stream = new Stream\ApplyStream($clone->stream, $operator);
        return $clone;
    }

    /**
     * @template TMap
     *
     * @param callable(TValue, int):TMap $onYield
     *
     * @return self<TMap>
     */
    public function map(callable $onYield): self
    {
        $clone = clone $this;
        $clone->stream = new Stream\MapStream($clone->stream, $onYield);
        return $clone;
    }

    /**
     * @param callable(TValue, int):bool $filter
     *
     * @return self<TValue>
     */
    public function filter(callable $filter): self
    {
        $clone = clone $this;
        $clone->stream = new Stream\FilterStream($clone->stream, $filter);
        return $clone;
    }

    /**
     * @param callable(TValue, int):void $onYield
     *
     * @return Promise<void>
     */
    public function each(callable $onYield): Promise
    {
        return (new EachOperator($this->stream, $onYield))->promise();
    }

    /**
     * @param int $count
     *
     * @return self<TValue>
     */
    public function drop(int $count): self
    {
        $clone = clone $this;
        $clone->stream = new Stream\DropStream($clone->stream, $count);
        return $clone;
    }

    /**
     * @param int $limit
     *
     * @return self<TValue>
     */
    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->stream = new Stream\LimitStream($clone->stream, $limit);
        return $clone;
    }

    /**
     * @return Promise<list<TValue>>
     */
    public function toArray(): Promise
    {
        return (new Stream\ToArrayOperator($this->stream))->promise();
    }
}
