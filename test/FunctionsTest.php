<?php

namespace AlertTest;

use Alert\Success;
use Alert\Failure;

class FunctionsTest extends \PHPUnit_Framework_TestCase {
    public function testAllResolvesWithArrayOfResults() {
        $promises = [
            'r1' => new Success(42),
            'r2' => new Success(41),
        ];

        $expected = ['r1' => 42, 'r2' => 41];
        $results = \Alert\all($promises)->wait();
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

        $results = \Alert\all($promises)->wait();
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        $promises = [
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ];

        list($errors, $results) = \Alert\some($promises)->wait();

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
        list($errors, $results) = \Alert\some($promises)->wait();
    }

    public function testResolveResolvesGeneratorResult() {
        $gen = function() {
            $a = (yield 21);
            $b = (yield new Success(2));
            yield ($a * $b);
        };

        $promise = \Alert\resolve($gen());
        $expected = 42;
        $actual = $promise->wait();
        $this->assertSame($expected, $actual);
    }
}
