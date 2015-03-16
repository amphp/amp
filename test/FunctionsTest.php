<?php

namespace Amp\Test;

use Amp\NativeReactor;
use Amp\Success;
use Amp\Failure;
use function Amp\all;
use function Amp\any;
use function Amp\some;
use function Amp\resolve;

class FunctionsTest extends \PHPUnit_Framework_TestCase {

    public function testAllResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        all($promises)->when(function($e, $r) {
            list($a, $b, $c, $d) = $r;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testSomeResolutionWhenNoPromiseInstancesCombined() {
        $promises = [null, 1, 2, true];
        some($promises)->when(function($e, $r) {
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
        any($promises)->when(function($e, $r) {
            list($errors, $results) = $r;
            list($a, $b, $c, $d) = $results;
            $this->assertNull($a);
            $this->assertSame(1, $b);
            $this->assertSame(2, $c);
            $this->assertSame(true, $d);
        });
    }

    public function testAllResolvesWithArrayOfResults() {
        all(['r1' => 42, 'r2' => new Success(41)])->when(function($error, $result) {
            $expected = ['r1' => 42, 'r2' => 41];
            $this->assertSame($expected, $result);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        $exception = new \RuntimeException('zanzibar');
        all([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function($error) {
            throw $error;
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        some([
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ])->when(function($error, $result) use ($exception) {
            list($errors, $results) = yield some($promises);
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        some([
            'r1' => new Failure(new \RuntimeException),
            'r2' => new Failure(new \RuntimeException),
        ])->when(function($error) {
            throw $error;
        });
    }

    public function testResolutionFailuresAreThrownIntoGenerator() {
        $foo = function() {
            $a = yield new Success(21);
            $b = 1;
            try {
                yield new Failure(new \Exception('test'));
                $this->fail('Code path should not be reached');
            } catch (\Exception $e) {
                $this->assertSame('test', $e->getMessage());
                $b = 2;
            }

            return ($a * $b);
        };

        $bar = function() use ($foo) {
            return yield from $foo();
        };

        (new NativeReactor)->run(function($reactor) use ($bar) {
            $result = yield resolve($bar(), $reactor);
            $this->assertSame(42, $result);
        });
    }
    
    
    
    
    
    
    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolverPromise() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                yield;
                throw new \Exception('When in the chronicle of wasted time');
                yield;
            };

            yield resolve($gen(), $reactor);
        });
    }

    public function testAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b) = yield all([
                    new Success(21),
                    new Success(2),
                ]);
                return ($a * $b);
            };

            $result = yield from $gen();
            $this->assertSame(42, $result);
        });
    }

    public function testAllCombinatorResolutionWithNonPromises() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = yield all([new Success(21), new Success(2), 10]);
                return ($a * $b * $c);
            };

            $result = yield from $gen();
            $this->assertSame(420, $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testAllCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b) = yield all([
                    new Success(21),
                    new Failure(new \Exception('When in the chronicle of wasted time')),
                ]);
            };
            yield from $gen();
        });
    }

    public function testExplicitAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = yield all([
                    new Success(21),
                    new Success(2),
                    10
                ]);
                return ($a * $b * $c);
            };

            $result = yield from $gen();
            $this->assertSame(420, $result);
        });
    }

    public function testExplicitAnyCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                return yield any([
                    'a' => new Success(21),
                    'b' => new Failure(new \Exception('test')),
                ]);
            };

            list($errors, $results) = yield from $gen();
            $this->assertSame('test', $errors['b']->getMessage());
            $this->assertSame(21, $results['a']);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                yield some([
                    'r1' => new Failure(new \RuntimeException),
                    'r2' => new Failure(new \RuntimeException),
                ]);
            };
            yield from $gen();
        });
    }

    
}
