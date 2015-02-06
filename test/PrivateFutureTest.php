<?php

namespace Amp\Test;

use Amp\PrivateFuture;
use Amp\NativeReactor;

class PrivateFutureTest extends \PHPUnit_Framework_TestCase {
    public function testPromiseReturnsUnresolvedInstance() {
        $promisor = new PrivateFuture;
        $this->assertInstanceOf('Amp\Unresolved', $promisor->promise());
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
            $next = new PrivateFuture;
            $reactor->once(function() use ($next) {
                $next->succeed(42);
            }, $msDelay = 1);
            $promisor->succeed($next->promise());
            $result = (yield $promisor->promise());
            $this->assertSame(42, $result);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage fugazi
     */
    public function testFailingWithPromisePipelinesResult() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new PrivateFuture;
            $next = new PrivateFuture;

            $reactor->once(function() use ($next) {
                $next->fail(new \RuntimeException('fugazi'));
            }, $msDelay = 10);

            $promisor->succeed($next->promise());

            yield $promisor->promise();
        });
    }

    public function testUpdate() {
        $updatable = 0;
        (new NativeReactor)->run(function() use (&$updatable) {
            $i = 0;
            $promisor = new PrivateFuture;
            $updater = function($reactor, $watcherId) use ($promisor, &$i) {
                $promisor->update(++$i);
                if ($i === 3) {
                    $reactor->cancel($watcherId);
                    // reactor run loop should now be able to exit
                }
            };
            $promise = $promisor->promise();
            $promise->watch(function($updateData) use (&$updatable) {
                $updatable += $updateData;
            });
            yield 'repeat' => [$updater, $msDelay = 10];
        });

        $this->assertSame(6, $updatable);
    }
}
