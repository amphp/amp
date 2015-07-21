<?php

namespace Amp\Test;

use Amp\NativeReactor;
use Amp\Coroutine;
use Amp\Success;
use Amp\Failure;
use Amp\Pause;

class CoroutineTest extends \PHPUnit_Framework_TestCase {
    public function testWrap() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $co = function($reactor) use (&$invoked) {
                yield new Success;
                yield;
                yield new Pause(25, $reactor);
                $invoked++;
            };
            $wrapped = Coroutine::wrap($co, $reactor);
            $wrapped($reactor);
        });
        $this->assertSame(1, $invoked);
    }
    
    public function testNestedResolutionContinuation() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $co = function() use (&$invoked) {
                yield new Success;
                yield new Success;
                yield new Success;
                yield new Success;
                yield new Success;
                yield Coroutine::result(42);
                $invoked++;
            };
            $result = (yield Coroutine::resolve($co(), $reactor));
            $this->assertSame(42, $result);
        });
        $this->assertSame(1, $invoked);
    }
    
    public function testCoroutineFauxReturnValue() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $co = function() use (&$invoked) {
                yield;
                yield Coroutine::result(42);
                yield;
                $invoked++;
            };
            $result = (yield Coroutine::resolve($co(), $reactor));
            $this->assertSame(42, $result);
        });
        $this->assertSame(1, $invoked);
    }

    public function testResolutionFailuresAreThrownIntoGenerator() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $foo = function() {
                $a = (yield new Success(21));
                $b = 1;
                try {
                    yield new Failure(new \Exception("test"));
                    $this->fail("Code path should not be reached");
                } catch (\Exception $e) {
                    $this->assertSame("test", $e->getMessage());
                    $b = 2;
                }
            };
            $result = (yield Coroutine::resolve($foo(), $reactor));
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage a moveable feast
     */
    public function testExceptionOnInitialAdvanceFailsResolution() {
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $co = function() {
                throw new \Exception("a moveable feast");
                yield;
            };
            $result = (yield Coroutine::resolve($co(), $reactor));
        });
    }

    /**
     * @dataProvider provideInvalidYields
     */
    public function testInvalidYieldFailsResolution($badYield) {
        try {
            (new NativeReactor)->run(function($reactor) use (&$invoked, $badYield) {
                $gen = function() use ($badYield) {
                    yield;
                    yield $badYield;
                    yield;
                };
                yield Coroutine::resolve($gen(), $reactor);
            });
            $this->fail("execution should not reach this point");
        } catch (\DomainException $e) {
            $pos = strpos($e->getMessage(), "Unexpected yield (Promise|CoroutineResult|null expected);");
            $this->assertSame(0, $pos);
            return;
        }
        $this->fail("execution should not reach this point");
    }

    public function provideInvalidYields() {
        return [
            [42],
            [3.14],
            ["string"],
            [true],
            [new \StdClass],
        ];
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolution() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $gen = function() {
                yield;
                throw new \Exception("When in the chronicle of wasted time");
                yield;
            };

            yield Coroutine::resolve($gen(), $reactor);
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }
}
