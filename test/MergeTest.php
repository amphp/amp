<?php

namespace Amp\Test;

use Amp;
use Amp\Emitter;
use Interop\Async\Loop;

class MergeTest extends \PHPUnit_Framework_TestCase {
    public function getObservables() {
        return [
            [[Amp\observableFromIterable(\range(1, 3)), Amp\observableFromIterable(\range(4, 6))], [1, 4, 2, 5, 3, 6]],
            [[Amp\observableFromIterable(\range(1, 5)), Amp\observableFromIterable(\range(6, 8))], [1, 6, 2, 7, 3, 8, 4, 5]],
            [[Amp\observableFromIterable(\range(1, 4)), Amp\observableFromIterable(\range(5, 10))], [1, 5, 2, 6, 3, 7, 4, 8, 9, 10]],
        ];
    }
    
    /**
     * @dataProvider getObservables
     *
     * @param array $observables
     * @param array $expected
     */
    public function testMerge(array $observables, array $expected) {
        Loop::execute(function () use ($observables, $expected) {
            $observable = Amp\merge($observables);
    
            Amp\each($observable, function ($value) use ($expected) {
                static $i = 0;
                $this->assertSame($expected[$i++], $value);
            });
        });
    }
    
    /**
     * @depends testMerge
     */
    public function testMergeWithFailedObservable() {
        $exception = new \Exception;
        Loop::execute(function () use (&$reason, $exception) {
            $emitter = new Emitter(function (callable $emit) use ($exception) {
                yield $emit(1); // Emit once before failing.
                throw $exception;
            });
    
            $observable = Amp\merge([$emitter, Amp\observableFromIterable(\range(1, 5))]);
    
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $observable->when($callback);
        });
        
        $this->assertSame($exception, $reason);
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-observable provided
     */
    public function testNonObservable() {
        Amp\merge([1]);
    }
}
