<?php

namespace Amp\Test;

use Amp\NativeReactor;
use Amp\Success;
use Amp\Failure;
use Amp\Deferred;
use Amp\PromiseStream;

class FunctionsTest extends \PHPUnit_Framework_TestCase {

    public function testPipe() {
        $invoked = 0;
        $promise = \Amp\pipe(21, function($r) { return $r * 2; });
        $promise->when(function($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertSame(42, $r);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPipeAbortsIfOriginalPromiseFails() {
        $invoked = 0;
        $failure = new Failure(new \RuntimeException);
        $promise = \Amp\pipe($failure, function(){});
        $promise->when(function($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertInstanceOf("RuntimeException", $e);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPipeAbortsIfFunctorThrows() {
        $invoked = 0;
        $promise = \Amp\pipe(42, function(){ throw new \RuntimeException; });
        $promise->when(function($e, $r) use (&$invoked) {
            $invoked++;
            $this->assertInstanceOf("RuntimeException", $e);
        });
        $this->assertSame(1, $invoked);
    }

    public function testAllResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        \Amp\all($promises)->when(function($e, $r) {
            list($a, $b, $c, $d) = $r;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testSomeResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        \Amp\some($promises)->when(function($e, $r) {
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
        \Amp\any($promises)->when(function($e, $r) {
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
        $this->assertInstanceOf("Amp\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($error);
        $this->assertSame([[], []], $result);
    }

    public function testAllResolvesWithArrayOfResults() {
        \Amp\all(['r1' => 42, 'r2' => new Success(41)])->when(function($error, $result) {
            $expected = ['r1' => 42, 'r2' => 41];
            $this->assertSame($expected, $result);
        });
    }

    public function testAllReturnsImmediatelyOnEmptyPromiseArray() {
        $promise = \Amp\all([]);
        $this->assertInstanceOf("Amp\Success", $promise);
        $error = null;
        $result = null;
        $promise->when(function($e, $r) use (&$error, &$result) {
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
        ])->when(function(\Exception $error) {
            throw $error;
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        \Amp\some([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function($error, $result) use ($exception) {
            list($errors, $results) = (yield \Amp\some($promises));
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }
    
    public function testSomeFailsImmediatelyOnEmptyPromiseArrayInput() {
        $promise = \Amp\some([]);
        $this->assertInstanceOf("Amp\Failure", $promise);
        $error = null;
        $result = null;
        $promise->when(function($e, $r) use (&$error, &$result) {
            $error = $e;
            $result = $r;
        });
        $this->assertNull($result);
        $this->assertInstanceOf("\LogicException", $error);
        $this->assertSame("No promises or values provided for resolution", $error->getMessage());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        \Amp\some([
            'r1' => new Failure(new \RuntimeException),
            'r2' => new Failure(new \RuntimeException),
        ])->when(function($error) {
            throw $error;
        });
    }

    public function testResolutionFailuresAreThrownIntoGenerator() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $foo = function() {
                $a = (yield new Success(21));
                $b = 1;
                try {
                    yield new Failure(new \Exception('test'));
                    $this->fail('Code path should not be reached');
                } catch (\Exception $e) {
                    $this->assertSame('test', $e->getMessage());
                    $b = 2;
                }
            };
            $result = (yield \Amp\resolve($foo(), $reactor));
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolverPromise() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $gen = function() {
                yield;
                throw new \Exception('When in the chronicle of wasted time');
                yield;
            };

            yield \Amp\resolve($gen(), $reactor);
            $invoked++;
        });
        $this->assertSame(1, $invoked);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Promise resolution timed out
     */
    public function testTimeout() {
        (new NativeReactor)->run(function($reactor) {
            $pause = new \Amp\Pause(1000, $reactor);
            yield \Amp\timeout($pause, 10, $reactor);
        });
    }

    public function testAllCombinatorResolution() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
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
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
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
        (new NativeReactor)->run(function($reactor) {
            list($a, $b) = (yield \Amp\all([
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]));
        });
    }

    public function testExplicitAllCombinatorResolution() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
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
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
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
     * @expectedException \RuntimeException
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        (new NativeReactor)->run(function($reactor) {
            yield \Amp\some([
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ]);
        });
    }

    public function testCoroutineFauxReturnValue() {
        $invoked = 0;
        (new NativeReactor)->run(function($reactor) use (&$invoked) {
            $co = function() use (&$invoked) {
                yield;
                yield "return" => 42;
                yield;
                $invoked++;
            };
            $result = (yield \Amp\resolve($co(), $reactor));
            $this->assertSame(42, $result);
        });
        $this->assertSame(1, $invoked);
    }

    public function testPromisesNormalization() {
        $completed = false;
        (new NativeReactor)->run(function($reactor) use (&$completed) {
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
}
