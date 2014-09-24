<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;
use Amp\Combinator;
use Amp\NativeReactor;

class CombinatorTest extends \PHPUnit_Framework_TestCase {

    private function getCombinator() {
        $reactor = new NativeReactor;
        return [new Combinator($reactor), $reactor];
    }

    public function testAllResolvesWithArrayOfResults() {
        $promises = [
            'r1' => new Success(42),
            'r2' => new Success(41),
        ];

        $expected = ['r1' => 42, 'r2' => 41];

        list($combinator) = $this->getCombinator();
        $results = $combinator->all($promises)->wait();
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

        list($combinator) = $this->getCombinator();
        $results = $combinator->all($promises)->wait();
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $exception = new \RuntimeException('zanzibar');
        $promises = [
            'r1' => new Success(42),
            'r2' => new Failure($exception),
            'r3' => new Success(40),
        ];

        list($combinator) = $this->getCombinator();
        list($errors, $results) = $combinator->some($promises)->wait();

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
        list($combinator) = $this->getCombinator();
        list($errors, $results) = $combinator->some($promises)->wait();
    }

    /**
     * @TODO testMap()
     */

    /**
     * @TODO testFilter()
     */
}
