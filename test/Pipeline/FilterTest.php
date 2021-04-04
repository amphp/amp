<?php

namespace Amp\Test\Pipeline;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Amp\PipelineSource;

class FilterTest extends AsyncTestCase
{
    public function testNoValuesEmitted(): void
    {
        $source = new PipelineSource;

        $pipeline = Pipeline\filter($source->pipe(), $this->createCallback(0));

        $source->complete();

        Pipeline\discard($pipeline)->join();
    }

    public function testValuesEmitted(): void
    {
        $count = 0;
        $values = [1, 2, 3];
        $expected = [1, 3];
        $generator = new AsyncGenerator(static function () use ($values) {
            foreach ($values as $value) {
                yield $value;
            }
        });

        $pipeline = Pipeline\filter($generator, static function ($value) use (&$count) {
            ++$count;

            return $value & 1;
        });

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }

        self::assertSame(3, $count);
    }

    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows(): void
    {
        $values = [1, 2, 3];
        $exception = new TestException;
        $generator = new AsyncGenerator(static function () use ($values) {
            foreach ($values as $value) {
                yield $value;
            }
        });

        $pipeline = Pipeline\filter($generator, static function () use ($exception) {
            throw $exception;
        });

        $this->expectExceptionObject($exception);

        $pipeline->continue();
    }

    public function testPipelineFails(): void
    {
        $exception = new TestException;
        $source = new PipelineSource;

        $pipeline = Pipeline\filter($source->pipe(), $this->createCallback(0));

        $source->error($exception);

        $this->expectExceptionObject($exception);

        $pipeline->continue();
    }
}
