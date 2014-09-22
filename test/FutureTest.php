<?php

namespace AlertTest;

use Alert\Future;
use Alert\NativeReactor;

class FutureTest extends \PHPUnit_Framework_TestCase {
    public function testPromiseReturnsSelf() {
        $future = new Future($this->getMock('Alert\Reactor'));
        $this->assertSame($future, $future->promise());
    }

    public function testWhenInvokesCallbackWithResultIfAlreadySucceeded() {
        $deferred = new Future($this->getMock('Alert\Reactor'));
        $promise = $deferred->promise();
        $deferred->succeed(42);
        $promise->when(function($e, $r) {
            $this->assertSame(42, $r);
            $this->assertNull($e);
        });
    }

    public function testWhenInvokesCallbackWithErrorIfAlreadyFailed() {
        $promisor = new Future($this->getMock('Alert\Reactor'));
        $promise = $promisor->promise();
        $exception = new \Exception('test');
        $promisor->fail($exception);
        $promise->when(function($e, $r) use ($exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($r);
        });
    }

    public function testWaitReturnsOnResolution() {
        $reactor = new NativeReactor;
        $promisor = new Future($reactor);
        $reactor->once(function() use ($promisor) { $promisor->succeed(42); }, $msDelay = 100);
        $this->assertSame(42, $promisor->promise()->wait());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testSucceedThrowsIfAlreadyResolved() {
        $promisor = new Future($this->getMock('Alert\Reactor'));
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = new Future($this->getMock('Alert\Reactor'));
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = new Future($this->getMock('Alert\Reactor'));
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        $reactor = new NativeReactor;
        $promisor = new Future($reactor);
        $next = new Future($reactor);

        $reactor->once(function() use ($next) {
            $next->succeed(42);
        }, $msDelay = 1);

        $promisor->succeed($next->promise());

        $this->assertSame(42, $promisor->promise()->wait());
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage fugazi
     */
    public function testFailingWithPromisePipelinesResult() {
        $reactor = new NativeReactor;
        $promisor = new Future($reactor);
        $next = new Future($reactor);

        $reactor->once(function() use ($next) {
            $next->fail(new \RuntimeException('fugazi'));
        }, $msDelay = 10);

        $promisor->succeed($next->promise());
        $promisor->promise()->wait();
    }
}
