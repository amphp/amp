<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\delay;
use function React\Promise\reject;

class PromiseTest extends AsyncTestCase
{
    /**
     * A Promise to use for a test with resolution methods.
     * Note that the callables shall take care of the Promise being resolved in any case. Example: The actual
     * implementation delays resolution to the next loop tick. The callables then must run one tick of the loop in
     * order to ensure resolution.
     *
     * @return array(Promise, callable, callable) where the last two callables are resolving the Promise with a result
     *     or a Throwable/Exception respectively
     */
    public function promise(): array
    {
        $deferred = new Deferred;
        return [
            $deferred->promise(),
            [$deferred, 'resolve'],
            [$deferred, 'fail'],
        ];
    }

    public function provideSuccessValues(): array
    {
        return [
            ["string"],
            [0],
            [~PHP_INT_MAX],
            [-1.0],
            [true],
            [false],
            [[]],
            [null],
            [new \StdClass],
            [new \Exception],
        ];
    }

    public function testPromiseImplementsPromise(): void
    {
        list($promise) = $this->promise();
        $this->assertInstanceOf(Promise::class, $promise);
    }

    /** @dataProvider provideSuccessValues */
    public function testPromiseSucceed(mixed $value): void
    {
        list($promise, $succeeder) = $this->promise();
        $promise->onResolve(function ($e, $v) use (&$invoked, $value) {
            $this->assertNull($e);
            $this->assertSame($value, $v);
            $invoked = true;
        });

        $succeeder($value);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    /** @dataProvider provideSuccessValues */
    public function testOnResolveOnSucceededPromise(mixed $value): void
    {
        list($promise, $succeeder) = $this->promise();
        $succeeder($value);
        $promise->onResolve(function ($e, $v) use (&$invoked, $value) {
            $this->assertNull($e);
            $this->assertSame($value, $v);
            $invoked = true;
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testSuccessAllOnResolvesExecuted(): void
    {
        list($promise, $succeeder) = $this->promise();
        $invoked = 0;

        $promise->onResolve(function ($e, $v) use (&$invoked) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;
        });
        $promise->onResolve(function ($e, $v) use (&$invoked) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;
        });

        $succeeder(true);

        $promise->onResolve(function ($e, $v) use (&$invoked) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;
        });
        $promise->onResolve(function ($e, $v) use (&$invoked) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(4, $invoked);
    }

    public function testPromiseExceptionFailure(): void
    {
        list($promise, , $failer) = $this->promise();
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked = true;
        });
        $failer(new \RuntimeException);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testOnResolveOnExceptionFailedPromise(): void
    {
        list($promise, , $failer) = $this->promise();
        $failer(new \RuntimeException);
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked = true;
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testFailureAllOnResolvesExecuted(): void
    {
        list($promise, , $failer) = $this->promise();
        $invoked = 0;

        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked++;
        });
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked++;
        });

        $failer(new \RuntimeException);

        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked++;
        });
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "RuntimeException");
            $invoked++;
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(4, $invoked);
    }

    public function testPromiseErrorFailure(): void
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped("Error only exists on PHP 7+");
        }

        list($promise, , $failer) = $this->promise();
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "Error");
            $invoked = true;
        });
        $failer(new \Error);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testOnResolveOnErrorFailedPromise(): void
    {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped("Error only exists on PHP 7+");
        }

        list($promise, , $failer) = $this->promise();
        $failer(new \Error);
        $promise->onResolve(function ($e) use (&$invoked) {
            $this->assertSame(\get_class($e), "Error");
            $invoked = true;
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    /** Implementations MAY fail upon resolution with a Promise, but they definitely MUST NOT return a Promise */
    public function testPromiseResolutionWithPromise(): void
    {
        list($success, $succeeder) = $this->promise();
        $succeeder(true);

        list($promise, $succeeder) = $this->promise();

        $ex = false;
        try {
            $succeeder($success);
        } catch (\Throwable $e) {
            $ex = true;
        } catch (\Exception $e) {
            $ex = true;
        }
        if (!$ex) {
            $promise->onResolve(function ($e, $v) use (&$invoked) {
                $invoked = true;
                $this->assertNotInstanceOf(Promise::class, $v);
            });

            delay(0); // Tick event loop to invoke onResolve callback.

            $this->assertTrue($invoked);
        }
    }

    public function testThrowingInCallback(): void
    {
        $invoked = 0;

        Loop::setErrorHandler(function () use (&$invoked) {
            $invoked++;
        });

        list($promise, $succeeder) = $this->promise();
        $succeeder(true);
        $promise->onResolve(function ($e, $v) use (&$invoked, $promise) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;

            throw new \Exception;
        });

        list($promise, $succeeder) = $this->promise();
        $promise->onResolve(function ($e, $v) use (&$invoked, $promise) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;

            throw new \Exception;
        });
        $succeeder(true);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(4, $invoked);
    }

    public function testThrowingInCallbackContinuesOtherOnResolves(): void
    {
        $invoked = 0;

        Loop::setErrorHandler(function () use (&$invoked) {
            $invoked++;
        });

        list($promise, $succeeder) = $this->promise();
        $promise->onResolve(function ($e, $v) use (&$invoked, $promise) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;

            throw new \Exception;
        });
        $promise->onResolve(function ($e, $v) use (&$invoked, $promise) {
            $this->assertNull($e);
            $this->assertTrue($v);
            $invoked++;
        });
        $succeeder(true);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(3, $invoked);
    }

    public function testThrowingInCallbackOnFailure(): void
    {
        $invoked = 0;
        Loop::setErrorHandler(function () use (&$invoked) {
            $invoked++;
        });

        list($promise, , $failer) = $this->promise();
        $exception = new \Exception;
        $failer($exception);
        $promise->onResolve(function ($e, $v) use (&$invoked, $exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($v);
            $invoked++;

            throw $e;
        });

        list($promise, , $failer) = $this->promise();
        $exception = new \Exception;
        $promise->onResolve(function ($e, $v) use (&$invoked, $exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($v);
            $invoked++;

            throw $e;
        });
        $failer($exception);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(4, $invoked);
    }

    public function testWeakTypes(): void
    {
        $invoked = 0;
        list($promise, $succeeder) = $this->promise();

        $expectedData = "15.24";

        $promise->onResolve(function ($e, int $v) use (&$invoked, $expectedData) {
            $invoked++;
            $this->assertSame((int) $expectedData, $v);
        });
        $succeeder($expectedData);
        $promise->onResolve(function ($e, int $v) use (&$invoked, $expectedData) {
            $invoked++;
            $this->assertSame((int) $expectedData, $v);
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(2, $invoked);
    }

    public function testResolvedQueueUnrolling(): void
    {
        $count = 50;
        $invoked = false;

        $deferred = new Deferred;
        $promise = $deferred->promise();
        $promise->onResolve(function () {
        });
        $promise->onResolve(function () {
        });
        $promise->onResolve(function () use (&$invoked) {
            $invoked = true;
            $this->assertLessThan(30, \count(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
        });

        $f = function () use (&$f, &$count, &$deferred) {
            $d = new Deferred;
            $p = $d->promise();
            $p->onResolve(function () {
            });
            $p->onResolve(function () {
            });

            $deferred->resolve($p);
            $deferred = $d;

            if (--$count > 0) {
                $f();
            }
        };

        $f();
        $deferred->resolve();

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testOnResolveWithReactPromise(): void
    {
        Loop::setErrorHandler(function (\Throwable $exception): void {
            $this->assertSame("Success", $exception->getMessage());
        });

        [$promise, $succeeder] = $this->promise();
        $promise->onResolve(function ($exception, $value) {
            return reject(new \Exception("Success"));
        });
        $succeeder();

        delay(0); // Tick event loop to invoke onResolve callback.
    }

    /**
     * @depends testOnResolveWithReactPromise
     */
    public function testOnResolveWithReactPromiseAfterResolve(): void
    {
        Loop::setErrorHandler(function (\Throwable $exception): void {
            $this->assertSame("Success", $exception->getMessage());
        });

        [$promise, $succeeder] = $this->promise();
        $succeeder();
        $promise->onResolve(function ($exception, $value) {
            return reject(new \Exception("Success"));
        });

        delay(0); // Tick event loop to invoke onResolve callback.
    }

    public function testOnResolveWithGenerator(): void
    {
        [$promise, $succeeder] = $this->promise();
        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $succeeder(1);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    /**
     * @depends testOnResolveWithGenerator
     */
    public function testOnResolveWithGeneratorAfterResolve(): void
    {
        [$promise, $succeeder] = $this->promise();
        $succeeder(1);
        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertTrue($invoked);
    }

    public function testOnResolveWithGeneratorWithMultipleCallbacks(): void
    {
        [$promise, $succeeder] = $this->promise();
        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked) {
            ++$invoked;
            return $value;
            yield; // Unreachable, but makes function a generator.
        };

        $promise->onResolve($callback);
        $promise->onResolve($callback);
        $promise->onResolve($callback);

        $succeeder(1);

        delay(0); // Tick event loop to invoke onResolve callback.

        $this->assertSame(3, $invoked);
    }
}
