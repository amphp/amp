<?php

namespace Amp\Test\Stream;

use Amp\AsyncGenerator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\StreamSource;

class FilterTest extends AsyncTestCase
{
    public function testNoValuesEmitted()
    {
        $source = new StreamSource;

        $stream = Stream\filter($source->stream(), $this->createCallback(0));

        $source->complete();

        yield Stream\discard($stream);
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

        $iterator = Stream\filter($generator, static function ($value) use (&$count) {
            ++$count;

            return $value & 1;
        });

        while ($value = yield $iterator->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
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

        $stream = Stream\filter($generator, static function () use ($exception) {
            throw $exception;
        });

        $this->expectExceptionObject($exception);

        yield $stream->continue();
    }

    public function testStreamFails()
    {
        $exception = new TestException;
        $source = new StreamSource;

        $stream = Stream\filter($source->stream(), $this->createCallback(0));

        $source->fail($exception);

        $this->expectExceptionObject($exception);

        yield $stream->continue();
    }
}
