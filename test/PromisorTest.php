<?php

namespace Amp\Test;

use Amp\NativeReactor;

abstract class PromisorTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getPromisor();

    protected function setUp() {
        \Amp\reactor($assign = new NativeReactor);
    }

    public function testWhenInvokesCallbackWithResultIfAlreadySucceeded() {
        $invoked = 0;
        $promisor = $this->getPromisor();
        $promise = $promisor->promise();
        $promisor->succeed(42);
        $promise->when(function ($e, $r) use (&$invoked) {
            $this->assertSame(42, $r);
            $this->assertNull($e);
            ++$invoked;
        });
        $this->assertSame(1, $invoked);
    }

    public function testWhenInvokesCallbackWithErrorIfAlreadyFailed() {
        $invoked = 0;
        $promisor = $this->getPromisor();
        $promise = $promisor->promise();
        $exception = new \Exception('test');
        $promisor->fail($exception);
        $promise->when(function ($e, $r) use ($exception, &$invoked) {
            $invoked++;
            $this->assertSame($exception, $e);
            $this->assertNull($r);
        });
        $this->assertSame(1, $invoked);
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
        \Amp\run(function () {
            $next = $this->getPromisor();
            $promisor = $this->getPromisor();
            $promisor->succeed($next->promise());
            \Amp\once(function () use ($next) {
                $next->succeed(42);
            }, $msDelay = 10);
            yield;
            $result = (yield $promisor->promise());
            $this->assertSame(42, $result);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage fugazi
     */
    public function testFailingWithPromisePipelinesResult() {
        \Amp\run(function () {
            $promisor = $this->getPromisor();
            $next = $this->getPromisor();
            \Amp\once(function () use ($next) {
                $next->fail(new \RuntimeException('fugazi'));
            }, $msDelay = 10);
            yield;
            $promisor->succeed($next->promise());
            yield $promisor->promise();
        });
    }

    public function testUpdate() {
        $updatable = 0;
        \Amp\run(function () use (&$updatable) {
            $i = 0;
            $promisor = $this->getPromisor();
            $updater = function ($watcherId) use ($promisor, &$i) {
                $promisor->update(++$i);
                if ($i === 3) {
                    \Amp\cancel($watcherId);
                    // reactor run loop should now be able to exit
                }
            };
            $promise = $promisor->promise();

            $promise->watch(function ($updateData) use (&$updatable) {
                $updatable += $updateData;
            });
            \Amp\repeat($updater, $msDelay = 10);
        });

        $this->assertSame(6, $updatable);
    }

    public function testUpdateArgs() {
        $updates = new \StdClass;
        $updates->arr = [];

        $promisor = $this->getPromisor();
        $promise = $promisor->promise();
        $promise->watch(function ($progress, $cbData) use ($updates) {
            $updates->arr[] = \func_get_args();
        }, "cb_data");

        $promisor->update(1);
        $promisor->update(2);
        $promisor->update(3);

        $expected = [
            [1, "cb_data"],
            [2, "cb_data"],
            [3, "cb_data"],
        ];

        $this->assertSame($expected, $updates->arr);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot update resolved promise
     */
    public function testUpdateThrowsIfPromiseAlreadyResolved() {
        $promisor = $this->getPromisor();
        $promisor->succeed();
        $promisor->update(42);
    }
}
