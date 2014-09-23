<?php

namespace Amp\Test;

use Amp\PrivateFuture;
use Amp\NativeReactor;

class PrivateFutureTest extends \PHPUnit_Framework_TestCase {
    public function testPromiseReturnsUnresolvedInstance() {
        $future = new PrivateFuture($this->getMock('Amp\Reactor'));
        $this->assertInstanceOf('Amp\Unresolved', $future->promise());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testSucceedThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture($this->getMock('Amp\Reactor'));
        $promisor->succeed(42);
        $promisor->succeed('zanzibar');
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage A Promise cannot act as its own resolution result
     */
    public function testSucceedThrowsIfPromiseIsTheResolutionValue() {
        $promisor = new PrivateFuture($this->getMock('Amp\Reactor'));
        $promise = $promisor->promise();
        $promisor->succeed($promise);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Promise already resolved
     */
    public function testFailThrowsIfAlreadyResolved() {
        $promisor = new PrivateFuture($this->getMock('Amp\Reactor'));
        $promisor->succeed(42);
        $promisor->fail(new \Exception);
    }

    public function testSucceedingWithPromisePipelinesResult() {
        $reactor = new NativeReactor;
        $promisor = new PrivateFuture($reactor);
        $next = new PrivateFuture($reactor);

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
        $next = new PrivateFuture($reactor);

        $reactor->once(function() use ($next) {
            $next->fail(new \RuntimeException('fugazi'));
        }, $msDelay = 10);

        $promisor->succeed($next->promise());
        $promisor->promise()->wait();
    }
}
