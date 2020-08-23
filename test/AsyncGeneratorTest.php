<?php

namespace Amp\Test;

use Amp\AsyncGenerator;
use Amp\Deferred;
use Amp\Delayed;
use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;

class AsyncGeneratorTest extends AsyncTestCase
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
        $value = 1;

        $generator = new AsyncGenerator(function (callable $yield) use ($value) {
            yield $yield($value);
        });

        $this->assertSame($value, yield $generator->continue());
    }

    public function testSend()
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
            $result = yield $yield($value);
        });

        $this->assertSame($value, yield $generator->continue());
        $this->assertNull(yield $generator->send($send));
        $this->assertSame($result, $send);
    }

    public function testSendBeforeYield()
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function (callable $yield) use (&$result, $value) {
            yield new Delayed(100); // Wait so send() is called before $yield().
            $result = yield $yield($value);
        });

        $promise1 = $generator->continue();
        $promise2 = $generator->send($send);

        $this->assertSame($value, yield $promise1);
        $this->assertNull(yield $promise2);
        $this->assertSame($result, $send);
    }

    public function testThrow()
    {
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

        $this->assertSame($value, yield $promise1);
        $this->assertNull(yield $promise2);
        $this->assertSame($result, $exception);
    }

    public function testThrowBeforeYield()
    {
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

        $this->assertSame($value, yield $generator->continue());
        $this->assertNull(yield $generator->throw($exception));
        $this->assertSame($result, $exception);
    }

    public function testInitialSend()
    {
        $generator = new AsyncGenerator(function (callable $yield) {
            yield $yield(0);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

        yield $generator->send(0);
    }

    public function testInitialThrow()
    {
        $generator = new AsyncGenerator(function (callable $yield) {
            yield $yield(0);
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

        yield $generator->throw(new \Exception);
    }

    public function testGetResult()
    {
        $value = 1;
        $generator = new AsyncGenerator(function (callable $yield) use ($value) {
            yield $yield(0);
            return $value;
        });

        $this->assertSame(0, yield $generator->continue());
        $this->assertNull(yield $generator->continue());
        $this->assertSame($value, yield $generator->getReturn());
    }

    /**
     * @depends testYield
     */
    public function testFailingPromise()
    {
        $exception = new TestException;
        $deferred = new Deferred();

        $generator = new AsyncGenerator(function (callable $yield) use ($deferred) {
            yield $yield(yield $deferred->promise());
        });

        $deferred->fail($exception);

        try {
            yield $generator->continue();
            $this->fail("Awaiting a failed promise should fail the pipeline");
        } catch (TestException $reason) {
            $this->assertSame($reason, $exception);
        }
    }

    /**
     * @depends testYield
     */
    public function testBackPressure()
    {
        $output = '';
        $yields = 5;

        $generator = new AsyncGenerator(function (callable $yield) use (&$time, $yields) {
            $time = \microtime(true);
            for ($i = 0; $i < $yields; ++$i) {
                yield $yield($i);
            }
            $time = \microtime(true) - $time;
        });

        while (null !== $yielded = yield $generator->continue()) {
            $output .= $yielded;
            yield new Delayed(self::TIMEOUT);
        }

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
            $generator = new AsyncGenerator(function (callable $yield) use ($exception) {
                yield $yield(1);
                throw $exception;
            });

            while (yield $generator->continue());
            $this->fail("The exception thrown from the coroutine should fail the pipeline");
        } catch (TestException $caught) {
            $this->assertSame($exception, $caught);
        }
    }

    public function testDisposal()
    {
        $generator = new AsyncGenerator(function (callable $yield) use (&$exception) {
            try {
                yield $yield(0);
            } catch (\Throwable $exception) {
                // Exception type validated below.
                return 1;
            }

            return 0;
        });

        $promise = $generator->getReturn();

        yield $generator->continue();

        unset($generator); // Should call dispose() on the internal pipeline.

        $this->assertInstanceOf(DisposedException::class, $exception);

        $this->assertSame(1, yield $promise);
    }

    /**
     * @depends testDisposal
     */
    public function testYieldAfterDisposal()
    {
        $generator = new AsyncGenerator(function (callable $yield) use (&$exception) {
            try {
                yield $yield(0);
            } catch (\Throwable $exception) {
                yield $yield(1);
            }
        });

        yield $generator->continue();

        $generator->dispose();

        $this->expectException(DisposedException::class);

        yield $generator->getReturn();
    }

    public function testLazyExecution()
    {
        $invoked = false;
        $generator = new AsyncGenerator(function (callable $yield) use (&$invoked) {
            $invoked = true;
            yield $yield(0);
        });

        $this->assertFalse($invoked);

        $this->assertSame(0, yield $generator->continue());

        $this->assertTrue($invoked);
    }
}
