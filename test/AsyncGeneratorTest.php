<?php

namespace Amp\Test;

use Amp\AsyncGenerator;
use Amp\Deferred;
use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

class AsyncGeneratorTest extends AsyncTestCase
{
    const TIMEOUT = 100;

    public function testNonGeneratorCallable(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('The callable did not return a Generator');

        new AsyncGenerator(function () {
        });
    }

    public function testYield(): void
    {
        $value = 1;

        $generator = new AsyncGenerator(function () use ($value) {
            yield $value;
        });

        $this->assertSame($value, $generator->continue());
    }

    public function testSend(): void
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            $result = yield $value;
        });

        $this->assertSame($value, $generator->continue());
        $this->assertNull($generator->send($send));
        $this->assertSame($result, $send);
    }

    public function testSendBeforeYield(): void
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            delay(100); // Wait so send() is called before $yield().
            $result = yield $value;
        });

        $promise1 = async(fn () => $generator->continue());
        $promise2 = async(fn () => $generator->send($send));

        $this->assertSame($value, await($promise1));
        $this->assertNull(await($promise2));
        $this->assertSame($result, $send);
    }

    public function testThrow(): void
    {
        $value = 1;
        $exception = new \Exception;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            try {
                $result = yield $value;
            } catch (\Throwable $exception) {
                $result = $exception;
            }
        });

        $promise1 = async(fn () => $generator->continue());
        $promise2 = async(fn () => $generator->throw($exception));

        $this->assertSame($value, await($promise1));
        $this->assertNull(await($promise2));
        $this->assertSame($result, $exception);
    }

    public function testThrowBeforeYield(): void
    {
        $value = 1;
        $exception = new \Exception;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            delay(100); // Wait so throw() is called before $yield().
            try {
                $result = yield $value;
            } catch (\Throwable $exception) {
                $result = $exception;
            }
        });

        $this->assertSame($value, $generator->continue());
        $this->assertNull($generator->throw($exception));
        $this->assertSame($result, $exception);
    }

    public function testInitialSend(): void
    {
        $generator = new AsyncGenerator(function () {
            yield 0;
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

        $generator->send(0);
    }

    public function testInitialThrow(): void
    {
        $generator = new AsyncGenerator(function () {
            yield 0;
        });

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Must initialize async generator by calling continue() first');

        $generator->throw(new \Exception);
    }

    public function testGetResult(): void
    {
        $value = 1;
        $generator = new AsyncGenerator(function () use ($value) {
            yield 0;
            return $value;
        });

        $this->assertSame(0, $generator->continue());
        $this->assertNull($generator->continue());
        $this->assertSame($value, $generator->getReturn());
    }

    /**
     * @depends testYield
     */
    public function testFailingPromise(): void
    {
        $exception = new TestException;
        $deferred = new Deferred;

        $generator = new AsyncGenerator(function () use ($deferred) {
            yield await($deferred->promise());
        });

        $deferred->fail($exception);

        try {
            $generator->continue();
            $this->fail("Awaiting a failed promise should fail the pipeline");
        } catch (TestException $reason) {
            $this->assertSame($reason, $exception);
        }
    }

    /**
     * @depends testYield
     */
    public function testBackPressure(): void
    {
        $output = '';
        $yields = 5;

        $generator = new AsyncGenerator(function () use (&$time, $yields) {
            $time = \microtime(true);
            for ($i = 0; $i < $yields; ++$i) {
                yield $i;
            }
            $time = \microtime(true) - $time;
        });

        while (null !== $yielded = $generator->continue()) {
            $output .= $yielded;
            delay(self::TIMEOUT);
        }

        $expected = \implode('', \range(0, $yields - 1));

        $this->assertSame($expected, $output);
        $this->assertGreaterThan(self::TIMEOUT * ($yields - 1), $time * 1000);
    }

    /**
     * @depends testYield
     */
    public function testAsyncGeneratorCoroutineThrows(): void
    {
        $exception = new TestException;

        try {
            $generator = new AsyncGenerator(function () use ($exception) {
                yield 1;
                throw $exception;
            });

            while ($generator->continue());
            $this->fail("The exception thrown from the generator should fail the pipeline");
        } catch (TestException $caught) {
            $this->assertSame($exception, $caught);
        }
    }

    public function testDisposal(): void
    {
        $invoked = false;
        $generator = new AsyncGenerator(function () use (&$invoked) {
            try {
                yield 0;
            } finally {
                $invoked = true;
            }
        });

        $promise = async(static fn () => $generator->getReturn());

        $this->assertSame(0, $generator->continue());

        $this->assertFalse($invoked);

        $generator->dispose();

        try {
            $this->assertSame(1, await($promise));
            $this->fail("Pipeline should have been disposed");
        } catch (DisposedException $exception) {
            $this->assertTrue($invoked);
        }
    }

    /**
     * @depends testDisposal
     */
    public function testYieldAfterDisposal(): void
    {
        $generator = new AsyncGenerator(function () use (&$exception) {
            try {
                yield 0;
            } catch (\Throwable $exception) {
                yield 1;
            }
        });

        $generator->continue();

        $generator->dispose();

        $this->expectException(DisposedException::class);

        $generator->getReturn();
    }

    public function testGeneratorStartsBeforeCallingContinue(): void
    {
        $invoked = false;
        $generator = new AsyncGenerator(function () use (&$invoked) {
            $invoked = true;
            yield 0;
        });

        $this->assertFalse($invoked);

        delay(0); // Tick event loop to start generator.

        $this->assertTrue($invoked);

        $this->assertSame(0, $generator->continue());
    }

    public function testTraversable(): void
    {
        $values = [];

        $generator = new AsyncGenerator(function () {
            yield 1;
            yield 2;
            yield 3;
        });

        foreach ($generator as $value) {
            $values[] = $value;
        }

        $this->assertSame([1, 2, 3], $values);
    }
}
