<?php

namespace Amp\Test\Pipeline;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Revolt\Future\Future;
use function Revolt\EventLoop\delay;
use function Revolt\Future\spawn;

class MergeTest extends AsyncTestCase
{
    public function getArrays(): array
    {
        return [
            [[\range(1, 3), \range(4, 6)], [1, 4, 2, 5, 3, 6]],
            [[\range(1, 5), \range(6, 8)], [1, 6, 2, 7, 3, 8, 4, 5]],
            [[\range(1, 4), \range(5, 10)], [1, 5, 2, 6, 3, 7, 4, 8, 9, 10]],
        ];
    }

    /**
     * @dataProvider getArrays
     *
     * @param array $array
     * @param array $expected
     */
    public function testMerge(array $array, array $expected): void
    {
        $pipelines = \array_map(static function (array $iterator): Pipeline {
            return Pipeline\fromIterable($iterator, 10);
        }, $array);

        $pipeline = Pipeline\merge($pipelines);

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithDelayedYields(): void
    {
        $pipelines = [];
        $values1 = [$this->asyncValue(10, 1), $this->asyncValue(50, 2), $this->asyncValue(70, 3)];
        $values2 = [$this->asyncValue(20, 4), $this->asyncValue(40, 5), $this->asyncValue(60, 6)];
        $expected = [1, 4, 5, 2, 6, 3];

        $pipelines[] = new AsyncGenerator(function () use ($values1) {
            foreach ($values1 as $value) {
                yield $value->join();
            }
        });

        $pipelines[] = new AsyncGenerator(function () use ($values2) {
            foreach ($values2 as $value) {
                yield $value->join();
            }
        });

        $pipeline = Pipeline\merge($pipelines);

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithFailedPipeline(): void
    {
        $exception = new TestException;
        $generator = new AsyncGenerator(static function () use ($exception) {
            yield 1; // Emit once before failing.
            throw $exception;
        });

        $pipeline = Pipeline\merge([$generator, $unused = Pipeline\fromIterable(\range(1, 5))]);

        try {
            Pipeline\discard($pipeline)->join();
            self::fail("The exception used to fail the pipeline should be thrown from continue()");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
        } finally {
            Pipeline\discard($unused)->join();
        }
    }

    public function testNonPipeline(): void
    {
        $this->expectException(\TypeError::class);

        /** @noinspection PhpParamsInspection */
        Pipeline\merge([1]);
    }

    private function asyncValue(int $delay, mixed $value): Future
    {
        return spawn(static function () use ($delay, $value): mixed {
            delay($delay);
            return $value;
        });
    }
}
