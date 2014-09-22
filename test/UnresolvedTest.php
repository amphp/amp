<?php

namespace AlertTest;

use Alert\PrivateFuture;
use Alert\Future;
use Alert\NativeReactor;

class UnresolvedTest extends \PHPUnit_Framework_TestCase {
    public function testWatchInvokesCallbackWithResultIfAlreadySucceeded() {
        $deferred = new PrivateFuture($this->getMock('Alert\Reactor'));
        $promise = $deferred->promise();
        $deferred->succeed(42);
        $promise->watch(function($p, $e, $r) {
            $this->assertSame(42, $r);
            $this->assertNull($p);
            $this->assertNull($e);
        });
    }

    public function testWatchInvokesCallbackWithErrorIfAlreadyFailed() {
        $promisor = new PrivateFuture($this->getMock('Alert\Reactor'));
        $promise = $promisor->promise();
        $exception = new \Exception('test');
        $promisor->fail($exception);
        $promise->watch(function($p, $e, $r) use ($exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($p);
            $this->assertNull($r);
        });
    }

    public function testWaitReturnsOnResolution() {
        $reactor = new NativeReactor;
        $promisor = new PrivateFuture($reactor);
        $reactor->once(function() use ($promisor) { $promisor->succeed(42); }, $msDelay = 100);
        $this->assertSame(42, $promisor->promise()->wait());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testSucceedThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture($this->getMock('Alert\Reactor'));
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = new PrivateFuture($this->getMock('Alert\Reactor'));
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture($this->getMock('Alert\Reactor'));
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        $reactor = new NativeReactor;
        $promisor = new PrivateFuture($reactor);
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
        $promisor = new PrivateFuture($reactor);
        $next = new Future($reactor);

        $reactor->once(function() use ($next) {
            $next->fail(new \RuntimeException('fugazi'));
        }, $msDelay = 10);

        $promisor->succeed($next->promise());
        $promisor->promise()->wait();
    }
}
