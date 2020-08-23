<?php

namespace Amp\Test\Pipeline;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Amp\PipelineSource;

class MapTest extends AsyncTestCase
{
    public function testNoValuesEmitted()
    {
        $source = new PipelineSource;

        /** @noinspection PhpUnusedLocalVariableInspection */
        $pipeline = Pipeline\map($source->pipe(), $this->createCallback(0));

        $source->complete();
    }

    public function testValuesEmitted()
    {
        $count = 0;
        $values = [1, 2, 3];
        $generator = new AsyncGenerator(static function (callable $yield) use ($values) {
            foreach ($values as $value) {
                yield $yield($value);
            }
        });

        $pipeline = Pipeline\map($generator, static function ($value) use (&$count) {
            ++$count;

            return $value + 1;
        });

        while (null !== $value = yield $pipeline->continue()) {
            $this->assertSame(\array_shift($values) + 1, $value);
        }

        $this->assertSame(3, $count);
    }

    /**
     * @depends testValuesEmitted
     */
    public function testOnNextCallbackThrows()
    {
        $values = [1, 2, 3];
        $exception = new TestException;

        $generator = new AsyncGenerator(static function (callable $yield) use ($values) {
            foreach ($values as $value) {
                yield $yield($value);
            }
        });

        $pipeline = Pipeline\map($generator, static function () use ($exception) {
            throw $exception;
        });

        $this->expectExceptionObject($exception);

        yield $pipeline->continue();
    }

    public function testPipelineFails()
    {
        $exception = new TestException;
        $source = new PipelineSource;

        $iterator = Pipeline\map($source->pipe(), $this->createCallback(0));

        $source->fail($exception);

        $this->expectExceptionObject($exception);

        yield $iterator->continue();
    }
}
