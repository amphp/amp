<?php

namespace Amp\Test;

use Amp\PrivateFuture;
use Amp\Future;
use Amp\NativeReactor;

class UnresolvedTest extends \PHPUnit_Framework_TestCase {
    public function testWatchInvokesCallbackWithResultIfAlreadySucceeded() {
        $promisor = new PrivateFuture;
        $promise = $promisor->promise();
        $promisor->succeed(42);
        $promise->watch(function($p, $e, $r) {
            $this->assertSame(42, $r);
            $this->assertNull($p);
            $this->assertNull($e);
        });
    }

    public function testWatchInvokesCallbackWithErrorIfAlreadyFailed() {
        $promisor = new PrivateFuture;
        $promise = $promisor->promise();
        $exception = new \Exception('test');
        $promisor->fail($exception);
        $promise->watch(function($p, $e, $r) use ($exception) {
            $this->assertSame($exception, $e);
            $this->assertNull($p);
            $this->assertNull($r);
        });
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testSucceedThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture;
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = new PrivateFuture;
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture;
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new PrivateFuture;
            $next = new Future;

            $reactor->once(function() use ($next) {
                $next->succeed(42);
            }, $msDelay = 1);

            $promisor->succeed($next->promise());

            $this->assertSame(42, (yield $promisor->promise()));
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage fugazi
     */
    public function testFailingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new PrivateFuture;
            $next = new Future;

            $reactor->once(function() use ($next) {
                $next->fail(new \RuntimeException('fugazi'));
            }, $msDelay = 10);

            $promisor->succeed($next->promise());
            yield $promisor->promise();
        });
    }
}
