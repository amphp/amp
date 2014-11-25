<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;
use Amp\NativeReactor;

class FunctionsTest extends \PHPUnit_Framework_TestCase {
    public function testAllResolvesWithArrayOfResults() {
        $promises = [
            'r1' => new Success(42),
            'r2' => new Success(41),
        ];

        $reactor = new NativeReactor;
        $expected = ['r1' => 42, 'r2' => 41];
        $results = \Amp\all($promises, $reactor)->wait();
        $this->assertSame($expected, $results);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        $exception = new \RuntimeException('zanzibar');
        $promises = [
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ];

        $reactor = new NativeReactor;
        $results = \Amp\all($promises, $reactor)->wait();
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        $promises = [
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ];

        $reactor = new NativeReactor;
        list($errors, $results) = \Amp\some($promises, $reactor)->wait();

        $this->assertSame(['r2' => $exception], $errors);
        $this->assertSame(['r1' => 42, 'r3' => 40], $results);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        $promises = [
            'r1' => new Failure(new \RuntimeException),
            'r2' => new Failure(new \RuntimeException),
        ];
        $reactor = new NativeReactor;
        list($errors, $results) = \Amp\some($promises, $reactor)->wait();
    }

    public function testResolveResolvesGeneratorResult() {
        $gen = function() {
            $a = (yield 21);
            $b = (yield new Success(2));
            yield ($a * $b);
        };

        $reactor = new NativeReactor;
        $promise = \Amp\resolve($gen(), $reactor);
        $expected = 42;
        $actual = $promise->wait();
        $this->assertSame($expected, $actual);
    }
















    // --- resolve() tests ------------------------------ //

    public function testResolve() {
        $gen = function() { yield 42; };

        $reactor = new NativeReactor;
        $result = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(42, $result);
    }

    public function testResolvedValueEqualsFinalYield() {
        $gen = function() {
            $a = (yield 21);
            $b = (yield new Success(2));
            yield ($a * $b);
        };

        $expected = 42;
        $reactor = new NativeReactor;
        $actual = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame($expected, $actual);
    }

    public function testFutureErrorsAreThrownIntoGenerator() {
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

        $expected = 42;
        $reactor = new NativeReactor;
        $actual = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame($expected, $actual);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolverPromise() {
        $gen = function() {
            yield;
            throw new \Exception('When in the chronicle of wasted time');
            yield;
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
    }

    public function testImplicitAllCombinatorResolution() {
        $gen = function() {
            list($a, $b) = (yield [new Success(21), new Success(2)]);
            yield ($a * $b);
        };

        $reactor = new NativeReactor;
        $result = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(42, $result);
    }

    public function testImplicitAllCombinatorResolutionWithNonPromises() {
        $gen = function() {
            list($a, $b, $c) = (yield [new Success(21), new Success(2), 10]);
            yield ($a * $b * $c);
        };

        $reactor = new NativeReactor;
        $result = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(420, $result);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testImplicitCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        $gen = function() {
            list($a, $b) = (yield [
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]);
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
    }

    public function testImplicitCombinatorResolvesGeneratorInArray() {
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

        $reactor = new NativeReactor;
        $result = \Amp\resolve($gen2(), $reactor)->wait();
        $this->assertSame(42, $result);
    }

    public function testExplicitAllCombinatorResolution() {
        $gen = function() {
            list($a, $b, $c) = (yield 'all' => [
                new Success(21),
                new Success(2),
                10
            ]);
            yield ($a * $b * $c);
        };

        $reactor = new NativeReactor;
        $result = \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(420, $result);
    }

    public function testExplicitAnyCombinatorResolution() {
        $gen = function() {
            yield 'any' => [
                'a' => new Success(21),
                'b' => new Failure(new \Exception('test')),
            ];
        };

        $reactor = new NativeReactor;
        list($errors, $results) = \Amp\resolve($gen(), $reactor)->wait();

        $this->assertSame('test', $errors['b']->getMessage());
        $this->assertSame(21, $results['a']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        $gen = function() {
            yield 'some' => [
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ];
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage "some" yield command expects array; string yielded
     */
    public function testExplicitCombinatorResolutionFailsIfNonArrayYielded() {
        $gen = function() {
            yield 'some' => 'test';
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
    }

    public function testExplicitImmediatelyYieldResolution() {
        $var = null;
        $gen = function() use (&$var) {
            yield 'immediately' => function() use (&$var) {
                $var = 42;
            };
            yield 'wait' => 100; // wait 100ms so the immediately callback executes
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(42, $var);
    }

    public function testExplicitOnceYieldResolution() {
        $var = null;
        $gen = function() use (&$var) {
            yield 'once' => [function() use (&$var) { $var = 42; }, $msDelay=1];
            yield 'wait' => 100; // wait 100ms so the once callback executes
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(42, $var);
    }

    public function testExplicitRepeatYieldResolution() {
        $var = 1;
        $repeatFunc = function($reactor, $watcherId) use (&$var) {
            yield 'cancel' => $watcherId;
            $var++;
        };

        $gen = function() use (&$var, $repeatFunc) {
            yield 'repeat' => [$repeatFunc, $msDelay = 1];
            yield 'wait'   => 100; // wait 100ms so we can be sure the repeat callback executes
        };

        $reactor = new NativeReactor;
        \Amp\resolve($gen(), $reactor)->wait();
        $this->assertSame(2, $var);
    }
}
