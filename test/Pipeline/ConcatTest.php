<?php

namespace Amp\Test\Pipeline;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;

class ConcatTest extends AsyncTestCase
{
    public function getArrays(): array
    {
        return [
            [[\range(1, 3), \range(4, 6)], \range(1, 6)],
            [[\range(1, 5), \range(6, 8)], \range(1, 8)],
            [[\range(1, 4), \range(5, 10)], \range(1, 10)],
        ];
    }

    /**
     * @dataProvider getArrays
     *
     * @param array $iterators
     * @param array $expected
     */
    public function testConcat(array $iterators, array $expected)
    {
        $iterators = \array_map(static function (array $iterator): Pipeline {
            return Pipeline\fromIterable($iterator);
        }, $iterators);

        $pipeline = Pipeline\concat($iterators);

        while (null !== $value = yield $pipeline->continue()) {
            $this->assertSame(\array_shift($expected), $value);
        }
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedPipeline()
    {
        $exception = new TestException;
        $expected = \range(1, 6);
        $generator = new AsyncGenerator(static function (callable $yield) use ($exception) {
            yield $yield(6); // Emit once before failing.
            throw $exception;
        });

        $pipeline = Pipeline\concat([
            Pipeline\fromIterable(\range(1, 5)),
            $generator,
            Pipeline\fromIterable(\range(7, 10)),
        ]);

        try {
            while (null !== $value = yield $pipeline->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }

            $this->fail("The exception used to fail the pipeline should be thrown from continue()");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
        }

        $this->assertEmpty($expected);
    }

    public function testNonPipeline()
    {
        $this->expectException(\TypeError::class);

        /** @noinspection PhpParamsInspection */
        Pipeline\concat([1]);
    }
}
