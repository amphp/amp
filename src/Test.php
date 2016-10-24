<?php

namespace Interop\Async\Awaitable;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

abstract class Test extends \PHPUnit_Framework_TestCase {
    /**
     * The DriverFactory to run this test on
     *
     * @return Loop\DriverFactory|null Use null to skip tests requiring an active event loop
     */
    abstract function getFactory();

    /**
     * An Awaitable to use for a test with resolution methods.
     * Note that the callables shall take care of the Awaitable being resolved in any case. Example: The actual implementation delays resolution to the next loop tick. The callables then must run one tick of the loop in order to ensure resolution.
     *
     * @return array(Awaitable, callable, callable) where the last two callables are resolving the Awaitable with a result or a Throwable/Exception respectively
     */
    abstract function getAwaitable();

    function startLoop($cb)
    {
        $factory = $this->getFactory();
        if ($factory === null) {
            $this->markTestSkipped("Skipping test needing event loop");
        }

        $loop = $factory->create();
        if (!$loop instanceof Loop\Driver) {
            $this->fail("Factory did not return a loop Driver");
        }

        Loop::execute($cb, $loop);
    }

    function provideSuccessValues() {
        return [
            ["string"],
            [0],
            [PHP_INT_MIN],
            [-1.0],
            [true],
            [false],
            [[]],
            [null],
            [new \StdClass],
        ];
    }

    /** @dataProvider provideSuccessValues */
    function testAwaitableSucceed($value)
    {
        list($awaitable, $succeeder) = $this->getAwaitable();
        $awaitable->when(function($e, $v) use (&$invoked, $value) {
            $this->assertSame(null, $e);
            $this->assertSame($value, $v);
            $invoked = true;
        });
        $succeeder($value);
        $this->assertTrue($invoked);
    }

    /** @dataProvider provideSuccessValues */
    function testWhenOnSucceededAwaitable($value) {
        list($awaitable, $succeeder) = $this->getAwaitable();
        $succeeder($value);
        $awaitable->when(function($e, $v) use (&$invoked, $value) {
            $this->assertSame(null, $e);
            $this->assertSame($value, $v);
            $invoked = true;
        });
        $this->assertTrue($invoked);
    }
    
    function testSuccessAllWhensExecuted() {
        list($awaitable, $succeeder) = $this->getAwaitable();
        $invoked = 0;
        
        $awaitable->when(function($e, $v) use (&$invoked) {
            $this->assertSame(null, $e);
            $this->assertSame(true, $v);
            $invoked++;
        });
        $awaitable->when(function($e, $v) use (&$invoked) {
            $this->assertSame(null, $e);
            $this->assertSame(true, $v);
            $invoked++;
        });

        $succeeder(true);

        $awaitable->when(function($e, $v) use (&$invoked) {
            $this->assertSame(null, $e);
            $this->assertSame(true, $v);
            $invoked++;
        });
        $awaitable->when(function($e, $v) use (&$invoked) {
            $this->assertSame(null, $e);
            $this->assertSame(true, $v);
            $invoked++;
        });
        
        $this->assertSame(4, $invoked);
    }

    function testAwaitableExceptionFailure() {
        list($awaitable, , $failer) = $this->getAwaitable();
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked = true;
        });
        $failer(new \RuntimeException);
        $this->assertTrue($invoked);
    }

    function testWhenOnExceptionFailedAwaitable() {
        list($awaitable, , $failer) = $this->getAwaitable();
        $failer(new \RuntimeException);
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked = true;
        });
        $this->assertTrue($invoked);
    }
    
    function testFailureAllWhensExecuted() {
        list($awaitable, , $failer) = $this->getAwaitable();
        $invoked = 0;

        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked++;
        });
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked++;
        });

        $failer(new \RuntimeException);

        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked++;
        });
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "RuntimeException");
            $invoked++;
        });

        $this->assertSame(4, $invoked);
    }

    function testAwaitableErrorFailure() {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped("Error only exists on PHP 7+");
        }

        list($awaitable, , $failer) = $this->getAwaitable();
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "Error");
            $invoked = true;
        });
        $failer(new \Error);
        $this->assertTrue($invoked);
    }

    function testWhenOnErrorFailedAwaitable() {
        if (PHP_VERSION_ID < 70000) {
            $this->markTestSkipped("Error only exists on PHP 7+");
        }

        list($awaitable, , $failer) = $this->getAwaitable();
        $failer(new \Error);
        $awaitable->when(function ($e) use (&$invoked) {
            $this->assertSame(get_class($e), "Error");
            $invoked = true;
        });
        $this->assertTrue($invoked);
    }

    /** Implementations MAY fail upon resolution with an Awaitable, but they definitely MUST NOT return an Awaitable */
    function testAwaitableResolutionWithAwaitable() {
        list($success, $succeeder) = $this->getAwaitable();
        $succeeder(true);

        list($awaitable, $succeeder) = $this->getAwaitable();

        $ex = false;
        try {
            $succeeder($success);
        } catch (\Throwable $e) {
            $ex = true;
        } catch (\Exception $e) {
            $ex = true;
        }
        if (!$ex) {
            $awaitable->when(function ($e, $v) use (&$invoked) {
                $invoked = true;
                $this->assertFalse($v instanceof Awaitable);
            });
            $this->assertTrue($invoked);
        }
    }

    function testThrowingInCallback() {
        $this->startLoop(function() use (&$ranDefer) {
            list($awaitable, $succeeder) = $this->getAwaitable();
            $succeeder(true);
            
            $invoked = 0;
            $awaitable->when(function($e, $v) use (&$invoked, &$ranDefer, &$handled, $awaitable) {
                $this->assertSame(null, $e);
                $this->assertSame(true, $v);
                $invoked++;

                Loop::defer(function() use (&$invoked, &$ranDefer, &$handled, $awaitable) {
                    $this->assertSame(2, $invoked);
                    $awaitable->when(function() use (&$ranDefer) {
                        $ranDefer = true;
                    });
                    $this->assertTrue($handled);
                });

                throw new \Exception;
            });
            $awaitable->when(function() use (&$invoked) {
                $invoked++;
            });

            Loop::setErrorHandler(function($e) use (&$handled) {
                if (get_class($e) !== "Exception") {
                    throw $e; // do not swallow phpunit exceptions due to failures
                }
                $handled = true;
            });
            $this->assertNotTrue($handled);
        });
        $this->assertTrue($ranDefer);
    }
}
