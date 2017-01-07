<?php

namespace Amp\Test;

use Amp;
use Amp\{ Deferred, Failure, Success };
use AsyncInterop\{ Loop, Promise };

class MapTest extends \PHPUnit_Framework_TestCase {
    public function testEmptyArray() {
        $values = [];
        $invoked = false;

        $result = Amp\map(function () use (&$invoked) {
            $invoked = true;
        }, $values);

        $this->assertSame($result, $values);
        $this->assertFalse($invoked);
    }

    public function testSuccessfulPromisesArray() {
        Loop::execute(Amp\wrap(function () {
            $promises = [new Success(1), new Success(2), new Success(3)];;

            $count = 0;
            $callback = function ($value) use (&$count) {
                ++$count;
                return $value - 1;
            };

            $result = Amp\map($callback, $promises);

            $this->assertTrue(\is_array($result));

            foreach ($result as $key => $promise) {
                $this->assertInstanceOf(Promise::class, $promise);
                $this->assertSame($key, Amp\wait($promise));
            }

            $this->assertSame(\count($promises), $count);
        }));
    }

    public function testPendingPromisesArray() {
        $deferreds = [
            new Deferred,
            new Deferred,
            new Deferred,
        ];

        $promises = \array_map(function (Deferred $deferred) {
            return $deferred->promise();
        }, $deferreds);

        $count = 0;
        $callback = function ($value) use (&$count) {
            ++$count;
            return $value - 1;
        };

        $result = Amp\map($callback, $promises);

        $this->assertTrue(\is_array($result));

        foreach ($deferreds as $key => $deferred) {
            $deferred->resolve($key + 1);
        }

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame($key, Amp\wait($promise));
        }

        $this->assertSame(\count($promises), $count);
    }

    public function testFailedPromisesArray() {
        Loop::execute(Amp\wrap(function () {
            $exception = new \Exception;
            $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];;

            $count = 0;
            $callback = function ($value) use (&$count) {
                ++$count;
                return $value - 1;
            };

            $result = Amp\map($callback, $promises);

            $this->assertTrue(\is_array($result));

            foreach ($result as $key => $promise) {
                $this->assertInstanceOf(Promise::class, $promise);
            }

            $this->assertSame(0, $count);
        }));
    }

    /**
     * @depends testFailedPromisesArray
     */
    public function testCallbackThrowingExceptionRejectsPromises()
    {
        Loop::execute(Amp\wrap(function () {
            $promises = [new Success(1), new Success(2), new Success(3)];;
            $exception = new \Exception;

            $callback = function () use ($exception) {
                throw $exception;
            };

            $result = Amp\map($callback, $promises);

            foreach ($result as $key => $promise) {
                $this->assertInstanceOf(Promise::class, $promise);
            }

            foreach ($result as $key => $promise) {
                try {
                    Amp\wait($promise);
                } catch (\Exception $reason) {
                    $this->assertSame($exception, $reason);
                }
            }
        }));
    }

    /**
     * @depends testPendingPromisesArray
     */
    public function testMultipleArrays() {
        $promises1 = [new Success(1), new Success(2), new Success(3)];;
        $promises2 = [new Success(3), new Success(2), new Success(1)];;

        $count = 0;
        $callback = function ($value1, $value2) use (&$count) {
            ++$count;
            return $value1 + $value2;
        };

        $result = Amp\map($callback, $promises1, $promises2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(Promise::class, $promise);
        }

        foreach ($result as $promise) {
            $this->assertInstanceOf(Promise::class, $promise);
            $this->assertSame(4, Amp\wait($promise));
        }
    }
}
