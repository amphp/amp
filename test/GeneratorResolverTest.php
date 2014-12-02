<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;
use Amp\NativeReactor;

class GeneratorResolverTest extends \PHPUnit_Framework_TestCase {
    public function testAllResolvesWithArrayOfResults() {
        (new NativeReactor)->run(function($reactor) {
            $expected = ['r1' => 42, 'r2' => 41];
            $actual = (yield 'all' => [
                'r1' => 42,
                'r2' => new Success(41),
            ]);
            $this->assertSame($expected, $actual);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        (new NativeReactor)->run(function($reactor) {
            $exception = new \RuntimeException('zanzibar');
            $promises = [
                'r1' => new Success(42),
                'r2' => new Failure($exception),
                'r3' => new Success(40),
            ];
            $results = (yield $promises);
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        (new NativeReactor)->run(function($reactor) {
            $exception = new \RuntimeException('zanzibar');
            $promises = [
                'r1' => new Success(42),
                'r2' => new Failure($exception),
                'r3' => new Success(40),
            ];
            list($errors, $results) = (yield 'some' => $promises);
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        (new NativeReactor)->run(function($reactor) {
            $promises = [
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ];
            list($errors, $results) = (yield 'some' => $promises);
        });
    }

    public function testResolvedValueEqualsFinalYield() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                $a = (yield 21);
                $b = (yield new Success(2));
                yield ($a * $b);
            };

            $result = (yield $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testFutureErrorsAreThrownIntoGenerator() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                $a = (yield 21);
                $b = 1;
                try {
                    yield new Failure(new \Exception('test'));
                    $this->fail('Code path should not be reached');
                } catch (\Exception $e) {
                    $this->assertSame('test', $e->getMessage());
                    $b = 2;
                }

                yield ($a * $b);
            };

            $result = (yield $gen());
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

            yield $gen();
        });
    }

    public function testImplicitAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b) = (yield [
                    new Success(21),
                    new Success(2),
                ]);
                yield ($a * $b);
            };

            $result = (yield $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testImplicitAllCombinatorResolutionWithNonPromises() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = (yield [new Success(21), new Success(2), 10]);
                yield ($a * $b * $c);
            };

            $result = (yield $gen());
            $this->assertSame(420, $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testImplicitAllCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        $gen = function() {
            list($a, $b) = (yield [
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]);
        };

        $reactor = new NativeReactor;
        $reactor->run(function($reactor) use ($gen) {
            yield $gen();
        });
    }

    public function testImplicitCombinatorResolvesGeneratorInArray() {
        (new NativeReactor)->run(function($reactor) {
            $gen1 = function() {
                yield 21;
            };

            $gen2 = function() use ($gen1) {
                list($a, $b) = (yield [
                    $gen1(),
                    new Success(2)
                ]);
                yield ($a * $b);
            };


            $result = (yield $gen2());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitAllCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = (yield 'all' => [
                    new Success(21),
                    new Success(2),
                    10
                ]);
                yield ($a * $b * $c);
            };

            $result = (yield $gen());
            $this->assertSame(420, $result);
        });
    }

    public function testExplicitAnyCombinatorResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                yield 'any' => [
                    'a' => new Success(21),
                    'b' => new Failure(new \Exception('test')),
                ];
            };

            list($errors, $results) = (yield $gen());
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
                yield 'some' => [
                    'r1' => new Failure(new \RuntimeException),
                    'r2' => new Failure(new \RuntimeException),
                ];
            };
            yield $gen();
        });
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage "some" yield command expects array; string yielded
     */
    public function testExplicitCombinatorResolutionFailsIfNonArrayYielded() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                yield 'some' => 'test';
            };
            yield $gen();
        });
    }

    public function testExplicitImmediatelyYieldResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                $var = null;
                yield 'immediately' => function() use (&$var) { $var = 42; };
                yield 'wait' => 100; // wait 100ms so the immediately callback executes
                yield $var;
            };
            $result = (yield $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitOnceYieldResolution() {
        (new NativeReactor)->run(function($reactor) {
            $gen = function() {
                $var = null;
                yield 'once' => [function() use (&$var) { $var = 42; }, $msDelay = 1];
                yield 'wait' => 100; // wait 100ms so the once callback executes
                yield $var;
            };
            $result = (yield $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitRepeatYieldResolution() {
        (new NativeReactor)->run(function($reactor) {
            $var = null;
            $repeatFunc = function($reactor, $watcherId) use (&$var) {
                $var = 1;
                yield 'cancel' => $watcherId;
                $var++;
            };

            $gen = function() use (&$var, $repeatFunc) {
                yield 'repeat' => [$repeatFunc, $msDelay = 1];
                yield 'wait'   => 100; // wait 100ms so we can be sure the repeat callback executes
                yield $var;
            };

            $result = (yield $gen());
            $this->assertSame(2, $result);
        });
    }
}
