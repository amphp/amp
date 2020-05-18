<?php

namespace Amp\Test;

use Amp\AsyncGenerator;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\YieldedValue;

class AsyncGeneratorTest extends BaseTest
{
    const TIMEOUT = 100;

    public function testNonGeneratorCallable()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The callable did not return a Generator');

        new AsyncGenerator(function () {
        });
    }

    public function testYield()
    {
        Loop::run(function () {
            $value = 1;

            $generator = new AsyncGenerator(function (callable $yield) use ($value) {
                yield $yield($value);
            });

            $yielded = yield $generator->continue();

            $this->assertInstanceOf(YieldedValue::class, $yielded);

            $this->assertSame($value, $yielded->unwrap());
        });
    }

    public function testSend()
    {
        Loop::run(function () {
            $value = 1;
            $send = 2;
            $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
                $result = yield $yield($value);
            });

            $this->assertSame($value, (yield $generator->continue())->unwrap());
            $this->assertNull(yield $generator->send($send));
            $this->assertSame($result, $send);
        });
    }

    public function testSendBeforeYield()
    {
        Loop::run(function () {
            $value = 1;
            $send = 2;
            $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
                yield new Delayed(100); // Wait so send() is called before $yield().
                $result = yield $yield($value);
            });

            $promise1 = $generator->continue();
            $promise2 = $generator->send($send);

            $this->assertSame($value, (yield $promise1)->unwrap());
            $this->assertNull(yield $promise2);
            $this->assertSame($result, $send);
        });
    }

    public function testThrow()
    {
        Loop::run(function () {
            $value = 1;
            $exception = new \Exception;
            $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
                try {
                    $result = yield $yield($value);
                } catch (\Throwable $exception) {
                    $result = $exception;
                }
            });

            $promise1 = $generator->continue();
            $promise2 = $generator->throw($exception);

            $this->assertSame($value, (yield $promise1)->unwrap());
            $this->assertNull(yield $promise2);
            $this->assertSame($result, $exception);
        });
    }

    public function testThrowBeforeYield()
    {
        Loop::run(function () {
            $value = 1;
            $exception = new \Exception;
            $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
                yield new Delayed(100); // Wait so throw() is called before $yield().
                try {
                    $result = yield $yield($value);
                } catch (\Throwable $exception) {
                    $result = $exception;
                }
            });

            $this->assertSame($value, (yield $generator->continue())->unwrap());
            $this->assertNull(yield $generator->throw($exception));
            $this->assertSame($result, $exception);
        });
    }

    public function testInitialSend()
    {
        Loop::run(function () {
            $generator = new AsyncGenerator(function (callable $yield) {
                yield $yield(0);
            });

            $this->expectException(\Error::class);
            $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

            yield $generator->send(0);
        });
    }

    public function testInitialThrow()
    {
        Loop::run(function () {
            $generator = new AsyncGenerator(function (callable $yield) {
                yield $yield(0);
            });

            $this->expectException(\Error::class);
            $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

            yield $generator->throw(new \Exception);
        });
    }

    public function testGetResult()
    {
        Loop::run(function () {
            $value = 1;
            $generator = new AsyncGenerator(function (callable $yield) use ($value) {
                yield $yield(null);
                return $value;
            });

            $this->assertNull((yield $generator->continue())->unwrap());
            $this->assertNull(yield $generator->continue());
            $this->assertSame($value, yield $generator->getReturn());
        });
    }

    /**
     * @depends testYield
     */
    public function testFailingPromise()
    {
        $exception = new TestException;
        Loop::run(function () use ($exception) {
            $deferred = new Deferred();

            $generator = new AsyncGenerator(function (callable $yield) use ($deferred) {
                yield $yield(yield $deferred->promise());
            });

            $deferred->fail($exception);

            try {
                yield $generator->continue();
                $this->fail("Awaiting a failed promise should fail the stream");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    /**
     * @depends testYield
     */
    public function testBackPressure()
    {
        $output = '';
        $yields = 5;
        Loop::run(function () use (&$time, &$output, $yields) {
            $generator = new AsyncGenerator(function (callable $yield) use (&$time, $yields) {
                $time = \microtime(true);
                for ($i = 0; $i < $yields; ++$i) {
                    yield $yield($i);
                }
                $time = \microtime(true) - $time;
            });

            while ($yielded = yield $generator->continue()) {
                $output .= $yielded->unwrap();
                yield new Delayed(self::TIMEOUT);
            }
        });

        $expected = \implode('', \range(0, $yields - 1));

        $this->assertSame($expected, $output);
        $this->assertGreaterThan(self::TIMEOUT * ($yields - 1), $time * 1000);
    }

    /**
     * @depends testYield
     */
    public function testAsyncGeneratorCoroutineThrows()
    {
        $exception = new TestException;

        try {
            Loop::run(function () use ($exception) {
                $generator = new AsyncGenerator(function (callable $yield) use ($exception) {
                    yield $yield(1);
                    throw $exception;
                });

                while (yield $generator->continue());
                $this->fail("The exception thrown from the coroutine should fail the stream");
            });
        } catch (TestException $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
