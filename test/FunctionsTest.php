<?php

namespace Amp\Test;

use Amp\CombinatorException;
use Amp\NativeReactor;
use Amp\Success;
use Amp\Failure;
use Amp\Deferred;
use Amp\PromiseStream;
use Amp\Pause;
use Amp\CoroutineResult;

class FunctionsTest extends \PHPUnit_Framework_TestCase {
    protected function setUp() {
        \Amp\reactor($assign = new NativeReactor);
    }

    public function testMap() {
        $promises = ["test1", new Success("test2"), new Success("test3")];
        $functor = "strtoupper";
        $promise = \Amp\map($promises, $functor);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame(["TEST1", "TEST2", "TEST3"], $result);
    }

    public function testMapReturnsEmptySuccessOnEmptyInput() {
        $promise = \Amp\map([], function () {});
        $this->assertInstanceOf("Amp\\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame([], $result);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage hulk smash
     */
    public function testMapFailsIfFunctorThrowsOnUnwrappedValue() {
        \Amp\run(function () {
            yield \Amp\map(["test"], function () { throw new \Exception("hulk smash"); });
        });
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage hulk smash
     */
    public function testMapFailsIfFunctorThrowsOnResolvedValue() {
        \Amp\run(function () {
            $promises = [new Success("test")];
            yield \Amp\map($promises, function () { throw new \Exception("hulk smash"); });
        });
    }

    public function testMapFailsIfAnyPromiseFails() {
        $e = new \RuntimeException(
            "true progress is to know more, and be more, and to do more"
        );
        $promise = \Amp\map([new Failure($e)], function () {});

        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertSame($e, $error);
        $this->assertNull($result);
    }

    public function testMapIgnoresFurtherResultsOnceFailed() {
        $completed = false;
        \Amp\run(function () use (&$completed) {
            $p1 = new Deferred;
            \Amp\once(function () use ($p1) {
                $p1->succeed("woot");
            }, 50);

            $e = new \RuntimeException(
                "true progress is to know more, and be more, and to do more"
            );

            $p2 = new Deferred;
            \Amp\once(function () use ($p2, $e) {
                $p2->fail($e);
            }, 10);

            $toMap = \Amp\promises([$p1, $p2]);

            try {
                yield \Amp\map($toMap, "strtoupper");
                $this->fail("this line should not be reached");
            } catch (\RuntimeException $error) {
                $this->assertSame($e, $error);
            }

            yield $p1->promise();

            $completed = true;
        });

        $this->assertTrue($completed);
    }

    public function testFilter() {
        $promises = ["test1", new Success("test2"), new Success("test2"), "test2"];
        $functor = function ($el) { return $el === "test2"; };
        $promise = \Amp\filter($promises, $functor);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame([1=>"test2", 2=>"test2", 3=>"test2"], $result);
    }

    public function testFilterUsesBoolComparisonOnUnspecifiedFunctor() {
        $promises = ["test1", new Success, null, false, 0, new Success("test2"), new Success(0), new Success(false) ];
        $promise = \Amp\filter($promises);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame([0=>"test1", 5=>"test2"], $result);
    }

    public function testFilter2() {
        $promises = ["test1", "test2", new Success("test2"), new Success("test2")];
        $functor = function ($el) { return $el === "test2"; };
        $promise = \Amp\filter($promises, $functor);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame([1=>"test2", 2=>"test2", 3=>"test2"], $result);
    }

    public function testFilterReturnsEmptySuccessOnEmptyInput() {
        $promise = \Amp\filter([], function () {});
        $this->assertInstanceOf("Amp\\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertNull($error);
        $this->assertSame([], $result);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage hulk smash
     */
    public function testFilterFailsIfFunctorThrowsOnUnwrappedValue() {
        \Amp\run(function () {
            yield \Amp\filter(["test"], function () { throw new \Exception("hulk smash"); });
        });
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage hulk smash
     */
    public function testFilterFailsIfFunctorThrowsOnResolvedValue() {
        \Amp\run(function () {
            $promises = [new Success("test")];
            yield \Amp\filter($promises, function () { throw new \Exception("hulk smash"); });
        });
    }

    public function testFilterFailsIfAnyPromiseFails() {
        $e = new \RuntimeException(
            "true progress is to know more, and be more, and to do more"
        );
        $promise = \Amp\filter([new Failure($e)], function () {});

        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });

        $this->assertSame($e, $error);
        $this->assertNull($result);
    }

    public function testFilterIgnoresFurtherResultsOnceFailed() {
        $completed = false;
        \Amp\run(function () use (&$completed) {
            $p1 = new Deferred;
            $p2 = new Deferred;
            $e = new \RuntimeException(
                "true progress is to know more, and be more, and to do more"
            );
            \Amp\once(function () use ($p1, $p2, $e) {
                $p2->fail($e);
                \Amp\immediately(function () use ($p1) {
                    $p1->succeed("woot");
                });
            }, 10);

            $toMap = \Amp\promises([$p1, $p2]);
            $functor = function () { return true; };

            try {
                yield \Amp\filter($toMap, $functor);
                $this->fail("this line should not be reached");
            } catch (\RuntimeException $error) {
                $this->assertSame($e, $error);
            }

            yield $p1->promise();

            $completed = true;
        });

        $this->assertTrue($completed);
    }

    public function testPipeWrapsRawValue() {
        $invoked = 0;
        $promise = \Amp\pipe(21, function ($r) { return $r * 2; });
        $promise->when(function ($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertSame(42, $r);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPipeTransformsEventualPromiseResult() {
        $result = 0;
        \Amp\run(function () use (&$result) {
            $promisor = new Deferred;
            \Amp\once(function () use ($promisor) {
                $promisor->succeed("woot");
            }, 10);

            $promise = $promisor->promise();
            $result = (yield \Amp\pipe($promise, "strtoupper"));
        });

        $this->assertSame("WOOT", $result);
    }

    public function testPipeAbortsIfOriginalPromiseFails() {
        $invoked = 0;
        $failure = new Failure(new \RuntimeException);
        $promise = \Amp\pipe($failure, function (){});
        $promise->when(function ($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertInstanceOf("RuntimeException", $e);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPipeAbortsIfFunctorThrowsOnRawValue() {
        $invoked = 0;
        $promise = \Amp\pipe(42, function (){ throw new \RuntimeException; });
        $promise->when(function ($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertInstanceOf("RuntimeException", $e);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPipeAbortsIfFunctorThrows() {
        $invoked = 0;
        $promise = \Amp\pipe(new Success(42), function (){ throw new \RuntimeException; });
        $promise->when(function ($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertInstanceOf("RuntimeException", $e);
        });
        $this->assertSame(1, $invoked);
    }

    public function testSomeResolutionWhenAllPromisesFail() {
        $ex1 = new \RuntimeException("1");
        $ex2 = new \RuntimeException("2");
        $promises = [new Failure($ex1), new Failure($ex2)];
        \Amp\some($promises)->when(function ($e, $r) use ($ex1, $ex2) {
            $this->assertNull($r);
            $this->assertInstanceOf(CombinatorException::class, $e);
            $this->assertSame($e->getExceptions()[0], $ex1);
            $this->assertSame($e->getExceptions()[1], $ex2);
        });
    }

    public function testAllResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        \Amp\all($promises)->when(function ($e, $r) {
            list($a, $b, $c, $d) = $r;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testSomeResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        \Amp\some($promises)->when(function ($e, $r) {
            list($errors, $results) = $r;
            list($a, $b, $c, $d) = $results;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testAnyResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        \Amp\any($promises)->when(function ($e, $r) {
            list($errors, $results) = $r;
            list($a, $b, $c, $d) = $results;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testAnyReturnsImmediatelyOnEmptyPromiseArray() {
        $promise = \Amp\any([]);
        $this->assertInstanceOf("Amp\\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($error);
        $this->assertSame([[], []], $result);
    }

    public function testAllResolvesWithArrayOfResults() {
        \Amp\all(['r1' => 42, 'r2' => new Success(41)])->when(function ($error, $result) {
            $expected = ['r1' => 42, 'r2' => 41];
            $this->assertSame($expected, $result);
        });
    }

    public function testAllReturnsImmediatelyOnEmptyPromiseArray() {
        $promise = \Amp\all([]);
        $this->assertInstanceOf("Amp\\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($error);
        $this->assertSame([], $result);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        $exception = new \RuntimeException('zanzibar');
        \Amp\all([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function (\Exception $error) {
            throw $error;
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        \Amp\some([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function ($error, $result) use ($exception) {
            $this->assertNull($error);
            list($errors, $results) = $result;
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }

    public function testSomeFailsImmediatelyOnEmptyPromiseArrayInput() {
        $promise = \Amp\some([]);
        $this->assertInstanceOf("Amp\\Failure", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($result);
        $this->assertInstanceOf("\\LogicException", $error);
        $this->assertSame("No promises or values provided for resolution", $error->getMessage());
    }

    /**
     * @expectedException \Amp\CombinatorException
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        \Amp\some([
            'r1' => new Failure(new \RuntimeException),
            'r2' => new Failure(new \RuntimeException),
        ])->when(function ($error) {
            throw $error;
        });
    }

    public function testFirstFailsImmediatelyOnEmptyPromiseArrayInput() {
        $promise = \Amp\first([]);
        $this->assertInstanceOf("Amp\\Failure", $promise);
        $error = null;
        $result = null;
        $promise->when(function ($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($result);
        $this->assertInstanceOf("\\LogicException", $error);
        $this->assertSame("No promises or values provided", $error->getMessage());
    }

    public function testFirst() {
        $resolutionCount = 0;
        $result = 0;
        \Amp\run(function () use (&$resolutionCount, &$result) {
            $p1 = new Deferred;
            \Amp\once(function () use ($p1, &$resolutionCount) {
                $p1->succeed(1);
                $resolutionCount++;
            }, 10);

            $p2 = new Deferred;
            \Amp\once(function () use ($p2, &$resolutionCount) {
                $p2->succeed(2);
                $resolutionCount++;
            }, 20);

            $p3 = new Deferred;
            \Amp\once(function () use ($p3, &$resolutionCount) {
                $p3->succeed(3);
                $resolutionCount++;
            }, 30);

            $promises = [$p1->promise(), $p2->promise(), $p3->promise()];
            $allPromise = \Amp\all($promises);
            $allPromise->when("\\Amp\\stop");

            $result = (yield \Amp\first($promises));
        });

        $this->assertSame(3, $resolutionCount);
        $this->assertSame(1, $result);
    }

    public function testNonPromiseValueImmediatelyResolvesFirstCombinator() {
        $result = 0;
        \Amp\run(function () use (&$result) {
            $p1 = 42;
            $p2 = (new Deferred)->promise();
            $result = (yield \Amp\first([$p1, $p2]));
        });
        $this->assertSame(42, $result);
    }

    /**
     * @expectedException \Amp\CombinatorException
     * @expectedExceptionMessage All promises failed
     */
    public function testFirstFailsIfAllPromisesFail() {
        \Amp\run(function () use (&$result) {
            $e1 = new \Exception("foo");
            $e2 = new \Exception("bar");
            $promises = [new Failure($e1), new Failure($e2)];
            yield \Amp\first($promises);
        });
    }

    /**
     * @expectedException \Amp\TimeoutException
     * @expectedExceptionMessage Promise resolution timed out
     */
    public function testTimeout() {
        \Amp\run(function () {
            $pause = new \Amp\Pause(3000);
            yield \Amp\timeout($pause, 10);
        });
    }

    public function testTimeoutOnSuccess() {
        $invoked = false;
        \Amp\run(function () use (&$invoked) {
            $promisor = new Deferred;
            \Amp\once(function () use ($promisor) {
                $promisor->succeed(42);
            }, 10);

            $result = (yield \Amp\timeout($promisor->promise(), 10000));
            $this->assertSame(42, $result);
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage nothing that is worth knowing can be taught
     */
    public function testTimeoutOnFailure() {
        \Amp\run(function () {
            $promisor = new Deferred;
            \Amp\once(function () use ($promisor) {
                $promisor->fail(new \RuntimeException(
                    "nothing that is worth knowing can be taught"
                ));
            }, 10);

            $result = (yield \Amp\timeout($promisor->promise(), 10000));
        });
    }

    public function testTimeoutIgnoresResultIfAlreadyComplete() {
        $invoked = false;
        \Amp\run(function () use (&$invoked) {
            $promisor = new Deferred;
            \Amp\once(function () use ($promisor) {
                $promisor->succeed(42);
            }, 100);
            try {
                $result = (yield \Amp\timeout($promisor->promise(), 10));
            } catch (\RuntimeException $e) {
                // ignore this
            }
            yield $promisor->promise();
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function testAllCombinatorResolution() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            list($a, $b) = (yield \Amp\all([
                    new Success(21),
                    new Success(2),
            ]));

            $result = ($a * $b);
            $this->assertSame(42, $result);
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    public function testAllCombinatorResolutionWithNonPromises() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            list($a, $b, $c) = (yield \Amp\all([new Success(21), new Success(2), 10]));
            $result = ($a * $b * $c);
            $this->assertSame(420, $result);
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testAllCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        \Amp\run(function () {
            list($a, $b) = (yield \Amp\all([
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]));
        });
    }

    public function testExplicitAllCombinatorResolution() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            list($a, $b, $c) = (yield \Amp\all([
                new Success(21),
                new Success(2),
                10
            ]));

            $this->assertSame(420, ($a * $b * $c));
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    public function testExplicitAnyCombinatorResolution() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            list($errors, $results) = (yield \Amp\any([
                'a' => new Success(21),
                'b' => new Failure(new \Exception('test')),
            ]));
            $this->assertSame('test', $errors['b']->getMessage());
            $this->assertSame(21, $results['a']);
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Amp\CombinatorException
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        \Amp\run(function () {
            yield \Amp\some([
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ]);
        });
    }

    public function testPromisesNormalization() {
        $completed = false;
        \Amp\run(function () use (&$completed) {
            $promisor = new Deferred;
            $promisor->succeed(41);
            $values = [
                $promisor,
                42,
                new Success(43),
            ];

            list($a, $b, $c) = (yield \Amp\all(\Amp\promises($values)));
            $this->assertSame(41, $a);
            $this->assertSame(42, $b);
            $this->assertSame(43, $c);
            $completed = true;
        });
        $this->assertTrue($completed);
    }

    public function testCoroutine() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            $co = function () use (&$invoked) {
                yield new Success;
                yield;
                yield new Pause(25);
                $invoked++;
            };
            $wrapped = \Amp\coroutine($co);
            $wrapped();
        });
        $this->assertSame(1, $invoked);
    }

    public function testCoroutineWrapsNonGeneratorReturnInPromise() {
        $co = \Amp\coroutine(function () {
            return 42;
        });
        $out = $co();
        $this->assertInstanceOf("\Amp\Success", $out);
        $invoked = false;
        $out->when(function ($error, $result) use (&$invoked) {
            $this->assertNull($error);
            $this->assertSame(42, $result);
            $invoked = true;
        });
        $this->assertTrue($invoked);
    }

    public function testCoroutineReturnsPromiseResultUnmodified() {
        $success = new \Amp\Success;
        $co = \Amp\coroutine(function () use ($success) {
            return $success;
        });
        $out = $co();
        $this->assertSame($success, $out);
    }

    public function testNestedCoroutineResolutionContinuation() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            $co = function () use (&$invoked) {
                yield new Success;
                yield new Success;
                yield new Success;
                yield new Success;
                yield new Success;
                yield new CoroutineResult(42);
                $invoked++;
            };
            $result = (yield \Amp\resolve($co()));
            $this->assertSame(42, $result);
        });
        $this->assertSame(1, $invoked);
    }

    public function testCoroutineFauxReturnValue() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            $co = function () use (&$invoked) {
                yield;
                yield new CoroutineResult(42);
                yield;
                $invoked++;
            };
            $result = (yield \Amp\resolve($co()));
            $this->assertSame(42, $result);
        });
        $this->assertSame(1, $invoked);
    }

    public function testResolutionFailuresAreThrownIntoGeneratorCoroutine() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            $foo = function () {
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
            $result = (yield \Amp\resolve($foo()));
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage a moveable feast
     */
    public function testExceptionOnInitialAdvanceFailsCoroutineResolution() {
        \Amp\run(function () use (&$invoked) {
            $co = function () {
                throw new \Exception("a moveable feast");
                yield;
            };
            $result = (yield \Amp\resolve($co()));
        });
    }

    /**
     * @dataProvider provideInvalidYields
     */
    public function testInvalidYieldFailsCoroutineResolution($badYield) {
        try {
            \Amp\run(function () use (&$invoked, $badYield) {
                $gen = function () use ($badYield) {
                    yield;
                    yield $badYield;
                    yield;
                };
                yield \Amp\resolve($gen());
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
    public function testUncaughtGeneratorExceptionFailsCoroutineResolution() {
        $invoked = 0;
        \Amp\run(function () use (&$invoked) {
            $gen = function () {
                yield;
                throw new \Exception("When in the chronicle of wasted time");
                yield;
            };

            yield \Amp\resolve($gen());
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    public function testWait() {
        $promisor = new Deferred;
        \Amp\once(function () use ($promisor) {
            $promisor->succeed(42);
        }, 10);

        $result = \Amp\wait($promisor->promise());
        $this->assertSame(42, $result);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage If the reader prefers, this code may be regarded as fiction
     */
    public function testWaitError() {
        $promisor = new Deferred;
        \Amp\once(function () use ($promisor) {
            $promisor->fail(new \Exception(
                "If the reader prefers, this code may be regarded as fiction"
            ));
        }, 10);

        \Amp\wait($promisor->promise());
    }
}






















