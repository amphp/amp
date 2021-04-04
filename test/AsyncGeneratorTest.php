<?php

namespace Amp\Test;

use Amp\AsyncGenerator;
use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Revolt\Future\Deferred;
use function Revolt\EventLoop\delay;
use function Revolt\Future\spawn;

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

        self::assertSame($value, $generator->continue());
        self::assertNull($generator->continue());
    }

    public function testSend(): void
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            $result = yield $value;
        });

        self::assertSame($value, $generator->continue());
        self::assertNull($generator->send($send));
        self::assertSame($result, $send);
    }

    public function testSendBeforeYield(): void
    {
        $value = 1;
        $send = 2;
        $generator = new AsyncGenerator(function () use (&$result, $value) {
            delay(100); // Wait so send() is called before $yield().
            $result = yield $value;
        });

        $future1 = spawn(fn () => $generator->continue());
        $future2 = spawn(fn () => $generator->send($send));

        self::assertSame($value, $future1->join());
        self::assertNull($future2->join());
        self::assertSame($result, $send);
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

        $future1 = spawn(fn () => $generator->continue());
        $future2 = spawn(fn () => $generator->throw($exception));

        self::assertSame($value, $future1->join());
        self::assertNull($future2->join());
        self::assertSame($result, $exception);
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

        self::assertSame($value, $generator->continue());
        self::assertNull($generator->throw($exception));
        self::assertSame($result, $exception);
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

        self::assertSame(0, $generator->continue());
        self::assertNull($generator->continue());
        self::assertSame($value, $generator->getReturn());
    }

    /**
     * @depends testYield
     */
    public function testFailingPromise(): void
    {
        $exception = new TestException;
        $deferred = new Deferred;

        $generator = new AsyncGenerator(function () use ($deferred) {
            yield $deferred->getFuture()->join();
        });

        $deferred->error($exception);

        try {
            $generator->continue();
            self::fail("Awaiting a failed promise should fail the pipeline");
        } catch (TestException $reason) {
            self::assertSame($reason, $exception);
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

        self::assertSame($expected, $output);
        self::assertGreaterThan(self::TIMEOUT * ($yields - 1), $time * 1000);
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

            while ($generator->continue()) {
                ;
            }
            self::fail("The exception thrown from the generator should fail the pipeline");
        } catch (TestException $caught) {
            self::assertSame($exception, $caught);
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

        $future = spawn(static fn () => $generator->getReturn());

        self::assertSame(0, $generator->continue());

        self::assertFalse($invoked);

        $generator->dispose();

        try {
            self::assertSame(1, $future->join());
            self::fail("Pipeline should have been disposed");
        } catch (DisposedException $exception) {
            self::assertTrue($invoked);
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

    public function testGeneratorStartsOnlyAfterCallingContinue(): void
    {
        $invoked = false;
        $generator = new AsyncGenerator(function () use (&$invoked) {
            $invoked = true;
            yield 0;
        });

        self::assertFalse($invoked);

        self::assertSame(0, $generator->continue());
        self::assertTrue($invoked);

        self::assertNull($generator->continue());
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

        self::assertSame([1, 2, 3], $values);
    }
}
