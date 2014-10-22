<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;
use Amp\Resolver;
use Amp\NativeReactor;

class ResolverTest extends \PHPUnit_Framework_TestCase {

    public function testGeneratorResolution() {
        $gen = function() { yield 42; };

        $resolver = new Resolver(new NativeReactor);
        $result = $resolver->resolve($gen())->wait();
        $this->assertSame(42, $result);
    }

    public function testResolvedValueEqualsFinalYield() {
        $gen = function() {
            $a = (yield 21);
            $b = (yield new Success(2));
            yield ($a * $b);
        };

        $expected = 42;
        $resolver = new Resolver(new NativeReactor);
        $actual = $resolver->resolve($gen())->wait();
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
        $resolver = new Resolver(new NativeReactor);
        $actual = $resolver->resolve($gen())->wait();
        $this->assertSame($expected, $actual);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsPromise() {
        $gen = function() {
            yield;
            throw new \Exception('When in the chronicle of wasted time');
            yield;
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
    }

    public function testImplicitAllCombinatorResolution() {
        $gen = function() {
            list($a, $b) = (yield [new Success(21), new Success(2)]);
            yield ($a * $b);
        };

        $resolver = new Resolver(new NativeReactor);
        $result = $resolver->resolve($gen())->wait();
        $this->assertSame(42, $result);
    }

    public function testImplicitAllCombinatorResolutionWithNonPromises() {
        $gen = function() {
            list($a, $b, $c) = (yield [new Success(21), new Success(2), 10]);
            yield ($a * $b * $c);
        };

        $resolver = new Resolver(new NativeReactor);
        $result = $resolver->resolve($gen())->wait();
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

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
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

        $resolver = new Resolver(new NativeReactor);
        $result = $resolver->resolve($gen2())->wait();
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

        $resolver = new Resolver(new NativeReactor);
        $result = $resolver->resolve($gen())->wait();
        $this->assertSame(420, $result);
    }

    public function testExplicitAnyCombinatorResolution() {
        $gen = function() {
            yield 'any' => [
                'a' => new Success(21),
                'b' => new Failure(new \Exception('test')),
            ];
        };

        $resolver = new Resolver(new NativeReactor);
        list($errors, $results) = $resolver->resolve($gen())->wait();

        $this->assertSame('test', $errors['b']->getMessage());
        $this->assertSame(21, $results['a']);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testExplicitSomeCombinatorFailsOnError() {
        $gen = function() {
            yield 'some' => [
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ];
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage "some" key expects array; string yielded
     */
    public function testExplicitCombinatorFailsIfNonArrayYielded() {
        $gen = function() {
            yield 'some' => 'test';
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
    }

    public function testExplicitImmediatelyYield() {
        $var = null;
        $gen = function() use (&$var) {
            yield 'immediately' => function() use (&$var) {
                $var = 42;
            };
            yield 'wait' => 100; // wait 100ms so the immediately callback executes
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
        $this->assertSame(42, $var);
    }

    public function testExplicitOnceYield() {
        $var = null;
        $gen = function() use (&$var) {
            yield 'once' => [function() use (&$var) { $var = 42; }, $msDelay=1];
            yield 'wait' => 100; // wait 100ms so the once callback executes
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
        $this->assertSame(42, $var);
    }

    public function testExplicitRepeatYield() {
        $var = 1;
        $gen = function() use (&$var) {
            yield 'repeat' => [function($reactor, $watcherId) use (&$var) {
                yield 'cancel' => $watcherId;
                $var++;
            }, $msDelay=1];
            yield 'wait' => 100; // wait 100ms so the once callback executes
        };

        $resolver = new Resolver(new NativeReactor);
        $resolver->resolve($gen())->wait();
        $this->assertSame(2, $var);
    }
}
