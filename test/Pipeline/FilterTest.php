<?php

namespace Amp\Test\Pipeline;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Amp\PipelineSource;

class FilterTest extends AsyncTestCase
{
    public function testNoValuesEmitted()
    {
        $source = new PipelineSource;

        $pipeline = Pipeline\filter($source->pipe(), $this->createCallback(0));

        $source->complete();

        yield Pipeline\discard($pipeline);
    }

    public function testValuesEmitted()
    {
        $count = 0;
        $values = [1, 2, 3];
        $expected = [1, 3];
        $generator = new AsyncGenerator(static function (callable $yield) use ($values) {
            foreach ($values as $value) {
                yield $yield($value);
            }
        });

        $pipeline = Pipeline\filter($generator, static function ($value) use (&$count) {
            ++$count;

            return $value & 1;
        });

        while (null !== $value = yield $pipeline->continue()) {
            $this->assertSame(\array_shift($expected), $value);
        }

        $this->assertSame(3, $count);
    }

    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows()
    {
        $values = [1, 2, 3];
        $exception = new TestException;
        $generator = new AsyncGenerator(static function (callable $yield) use ($values) {
            foreach ($values as $value) {
                yield $yield($value);
            }
        });

        $pipeline = Pipeline\filter($generator, static function () use ($exception) {
            throw $exception;
        });

        $this->expectExceptionObject($exception);

        yield $pipeline->continue();
    }

    public function testPipelineFails()
    {
        $exception = new TestException;
        $source = new PipelineSource;

        $pipeline = Pipeline\filter($source->pipe(), $this->createCallback(0));

        $source->fail($exception);

        $this->expectExceptionObject($exception);

        yield $pipeline->continue();
    }
}
