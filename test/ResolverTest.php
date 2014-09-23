<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;
use Amp\Resolver;
use Amp\NativeReactor;

class ResolverTest extends \PHPUnit_Framework_TestCase {

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
        $promise = $resolver->resolve($gen());
        $promise->when(function($error, $result) {
            throw $error;
        });
    }
}
