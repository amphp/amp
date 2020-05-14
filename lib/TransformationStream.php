<?php

namespace Amp;

/**
 * @template TValue
 * @template-implements Stream<TValue>
 */
final class TransformationStream implements Stream
{
    /** @var Stream<TValue> */
    private $stream;

    public function __construct(Stream $stream, callable $operator = null)
    {
        $this->stream = $stream instanceof self ? $stream->stream : $stream;

        if ($operator !== null) {
            $this->stream = $this->apply($operator);
        }
    }

    public function continue(): Promise
    {
        return $this->stream->continue();
    }

    public function dispose()
    {
        $this->stream->dispose();
    }

    public function transform(callable $operator = null): self
    {
        if ($operator === null) {
            return $this;
        }

        return new self($this->apply($operator));
    }

    private function apply(callable $operator): self
    {
        $stream = $operator($this);

        if ($stream instanceof Stream) {
            throw new \TypeError('$operator must return an instance of ' . Stream::class);
        }

        return $stream;
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
        return new self(new AsyncGenerator(function (callable $yield) use ($onYield): \Generator {
            while (list($value, $key) = yield $this->stream->continue()) {
                yield $yield(yield call($onYield, $value, $key));
            }
        }));
    }

    /**
     * @param callable(TValue, int):bool $filter
     *
     * @return self<TValue>
     */
    public function filter(callable $filter): self
    {
        return new self(new AsyncGenerator(function (callable $yield) use ($filter) {
            while (list($value, $key) = yield $this->stream->continue()) {
                if (yield call($filter, $value, $key)) {
                    yield $yield($value, $key);
                }
            }
        }));
    }

    /**
     * @param callable(TValue, int):void $onYield
     *
     * @return Promise<void>
     */
    public function each(callable $onYield): Promise
    {
        return call(function () use ($onYield) {
            while (list($value, $key) = yield $this->stream->continue()) {
                yield call($onYield, $value, $key);
            }
        });
    }

    /**
     * @param int $count
     *
     * @return self<TValue>
     */
    public function drop(int $count): self
    {
        return new self(new AsyncGenerator(function (callable $yield) use ($count) {
            $skipped = 0;
            while (list($value) = yield $this->stream->continue()) {
                if (++$skipped <= $count) {
                    continue;
                }

                yield $yield($value);
            }
        }));
    }

    /**
     * @param int $limit
     *
     * @return self<TValue>
     */
    public function limit(int $limit): Stream
    {
        return new self(new AsyncGenerator(function (callable $yield) use ($limit) {
            $yielded = 0;
            while (list($value) = yield $this->stream->continue()) {
                if (++$yielded > $limit) {
                    $this->stream->dispose();
                    return;
                }

                yield $yield($value);
            }
        }));
    }

    /**
     * @return Promise<list<TValue>>
     */
    public function toArray(): Promise
    {
        return call(static function (): \Generator {
            /** @psalm-var list $array */
            $array = [];

            while (list($value) = yield $this->stream->continue()) {
                $array[] = $value;
            }

            return $array;
        });
    }
}
