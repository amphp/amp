<?php

namespace Amp\Test;

use Amp\Coroutine;
use Amp\Success;

/**
 * @todo Combine with CoroutineTest once PHP 7 is required.
 */
class ExtendedCoroutineTest extends \PHPUnit_Framework_TestCase {
    public function testCoroutineResolvedWithReturn() {
        $value = 1;

        $generator = function () use ($value) {
            return $value;
            yield; // Unreachable, but makes function a coroutine.
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });


        $this->assertSame($value, $result);
    }

    /**
     * @depends testCoroutineResolvedWithReturn
     */
    public function testYieldFromGenerator() {
        $value = 1;

        $generator = function () use ($value) {
            $generator = function () use ($value) {
                return yield new Success($value);
            };

            return yield from $generator();
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });


        $this->assertSame($value, $result);
    }

    /**
     * @depends testCoroutineResolvedWithReturn
     */
    public function testFastReturningGenerator()
    {
        $value = 1;

        $generator = function () use ($value) {
            if (true) {
                return $value;
            }

            yield;

            return -$value;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertSame($value, $result);
    }

}