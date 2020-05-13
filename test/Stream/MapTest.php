<?php

namespace Amp\Test\Stream;

use Amp\AsyncGenerator;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\StreamSource;
use Amp\Test\BaseTest;

class MapTest extends BaseTest
{
    public function testNoValuesEmitted()
    {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $source = new StreamSource;

            $iterator = Stream\map($source->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $this->assertInstanceOf(Stream::class, $iterator);

            $source->complete();
        });

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted()
    {
        Loop::run(function () {
            $count = 0;
            $values = [1, 2, 3];
            $generator = new AsyncGenerator(function (callable $yield) use ($values) {
                foreach ($values as $value) {
                    yield $yield($value);
                }
            });

            $iterator = Stream\map($generator, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            });

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($values) + 1, $value);
            }

            $this->assertSame(3, $count);
        });
    }

    /**
     * @depends testValuesEmitted
     */
    public function testOnNextCallbackThrows()
    {
        Loop::run(function () {
            $values = [1, 2, 3];
            $exception = new TestException;

            $generator = new AsyncGenerator(function (callable $yield) use ($values) {
                foreach ($values as $value) {
                    yield $yield($value);
                }
            });

            $iterator = Stream\map($generator, function () use ($exception) {
                throw $exception;
            });

            try {
                yield $iterator->continue();
                $this->fail("The exception thrown from the map callback should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    public function testStreamFails()
    {
        Loop::run(function () {
            $invoked = false;
            $exception = new TestException;
            $source = new StreamSource;

            $iterator = Stream\map($source->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $source->fail($exception);

            try {
                yield $iterator->continue();
                $this->fail("The exception used to fail the iterator should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }

            $this->assertFalse($invoked);
        });
    }
}
