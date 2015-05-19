<?php

namespace Amp\Test;

use Amp\NativeReactor;

abstract class PlaceholderTest  {
    abstract protected function getPromisor();

    public function testWatchInvokesCallbackWithResultIfAlreadySucceeded() {
        $promisor = $this->getPromisor();
        $promise = $promisor->promise();
        $promisor->succeed(42);
        $promise->watch(function($p, $e, $r) {
            $this->assertSame(42, $r);
            $this->assertNull($p);
            $this->assertNull($e);
        });
    }

    public function testWatchInvokesCallbackWithErrorIfAlreadyFailed() {
        $promisor = $this->getPromisor();
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
        $promisor = $this->getPromisor();
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = $this->getPromisor();
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = $this->getPromisor();
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = $this->getPromisor();
            $next = $this->getPromisor();

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
            $promisor = $this->getPromisor();
            $next = $this->getPromisor();

            $reactor->once(function() use ($next) {
                $next->fail(new \RuntimeException('fugazi'));
            }, $msDelay = 10);

            $promisor->succeed($next->promise());
            yield $promisor->promise();
        });
    }

}
