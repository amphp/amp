<?php

namespace Amp\Test;

use Amp\Future;
use Amp\NativeReactor;

class FutureTest extends \PHPUnit_Framework_TestCase {
    public function testPromiseReturnsSelf() {
        $promisor = new Future;
        $this->assertSame($promisor, $promisor->promise());
    }

    public function testWhenInvokesCallbackWithResultIfAlreadySucceeded() {
        $promisor = new Future;
        $promise = $promisor->promise();
        $promisor->succeed(42);
        $promise->when(function($e, $r) {
            $this->assertSame(42, $r);
            $this->assertNull($e);
        });
    }

    public function testWhenInvokesCallbackWithErrorIfAlreadyFailed() {
        $promisor = new Future;
        $promise = $promisor->promise();
        $exception = new \Exception('test');
        $promisor->fail($exception);
        $promise->when(function($e, $r) use ($exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($r);
        });
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testSucceedThrowsIfAlreadyResolved() {
        $promisor = new Future;
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = new Future;
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = new Future;
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function() {
            $next = new Future;
            $promisor = new Future;
            $promisor->succeed($next->promise());
            yield 'once' => [function() use ($next) { $next->succeed(42); }, $msDelay = 10];
            $result = (yield $promisor->promise());
            $this->assertSame(42, $result);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage fugazi
     */
    public function testFailingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function() {
            $promisor = new Future;
            $next = new Future;
            $once = function() use ($next) { $next->fail(new \RuntimeException('fugazi')); };
            yield 'once' => [$once, $msDelay = 10];
            $promisor->succeed($next->promise());

            yield $promisor->promise();
        });
    }
}
