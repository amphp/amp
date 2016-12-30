<?php

namespace Amp\Test;

use Amp;
use Amp\Emitter;
use Interop\Async\Loop;

class ConcatTest extends \PHPUnit_Framework_TestCase {
    public function getObservables() {
        return [
            [[Amp\observableFromIterable(\range(1, 3)), Amp\observableFromIterable(\range(4, 6))], \range(1, 6)],
            [[Amp\observableFromIterable(\range(1, 5)), Amp\observableFromIterable(\range(6, 8))], \range(1, 8)],
            [[Amp\observableFromIterable(\range(1, 4)), Amp\observableFromIterable(\range(5, 10))], \range(1, 10)],
        ];
    }
    
    /**
     * @dataProvider getObservables
     *
     * @param array $observables
     * @param array $expected
     */
    public function testConcat(array $observables, array $expected) {
        Loop::execute(function () use ($observables, $expected) {
            $observable = Amp\concat($observables);
    
            Amp\each($observable, function ($value) use ($expected) {
                static $i = 0;
                $this->assertSame($expected[$i++], $value);
            });
        });
    }
    
    /**
     * @depends testConcat
     */
    public function testConcatWithFailedObservable() {
        $exception = new \Exception;
        $results = [];
        Loop::execute(function () use (&$results, &$reason, $exception) {
            $emitter = new Emitter(function (callable $emit) use ($exception) {
                yield $emit(6); // Emit once before failing.
                throw $exception;
            });
    
            $observable = Amp\concat([Amp\observableFromIterable(\range(1, 5)), $emitter, Amp\observableFromIterable(\range(7, 10))]);
            
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
            
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $observable->when($callback);
        });
        
        $this->assertSame(\range(1, 6), $results);
        $this->assertSame($exception, $reason);
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-observable provided
     */
    public function testNonObservable() {
        Amp\concat([1]);
    }
}
