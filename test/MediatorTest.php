<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Mediator;
use Amp\MultiReasonException;
use PHPUnit\Framework\TestCase;

class MediatorTest extends TestCase {
    public function testNoSubscriberInvocation() {
        $result = null;
        $loopRan = false;
        $wasInvoked = false;

        Loop::run(function () use (&$result, &$loopRan, &$wasInvoked) {
            $loopRan = true;
            $mediator = new Mediator;
            $mediator->subscribe("this-event", static function () use (&$wasInvoked) {
                $wasInvoked = true;
            });
            $result = yield $mediator->publish("that-event", 42);
        });

        $this->assertTrue($loopRan, "Loop did not run");
        $this->assertFalse($wasInvoked, "Subscriber was invoked but shouldn't have been");
        $this->assertSame(0, $result);
    }

    public function testSingleSubscriberInvocation() {
        $result = null;

        Loop::run(function () use (&$result) {
            $loopRan = true;
            $mediator = new Mediator;
            $mediator->subscribe("this-event", static function () {});
            $result = yield $mediator->publish("this-event", 42);
        });

        $this->assertSame(1, $result);
    }

    public function testMultiSubscriberInvocation() {
        $result = null;

        Loop::run(function () use (&$result) {
            $mediator = new Mediator;
            $mediator->subscribe("this-event", static function () {});
            $mediator->subscribe("this-event", static function () {});
            $result = yield $mediator->publish("this-event", 42);
        });

        $this->assertSame(2, $result);
    }

    public function testMultiSubscriberErrorCase() {
        $invocations = 0;
        $caughtExpected = false;

        Loop::run(function () use (&$invocations, &$caughtExpected) {
            $mediator = new Mediator;
            $f1 = static function () use (&$invocations) {
                $invocations++;
                throw new \Exception;
            };
            $f2 = static function () use (&$invocations) {
                $invocations++;
                throw new \Exception;
            };
            $mediator->subscribe("this-event", $f1);
            $mediator->subscribe("this-event", $f2);
            try {
                yield $mediator->publish("this-event", 42);
            } catch (MultiReasonException $e) {
                // do this instead of $this->expectException() so we can validate
                // that both subscribers are called despite the exceptions
                $caughtExpected = true;
            }
        });

        $this->assertTrue($caughtExpected);
        $this->assertSame(2, $invocations);
    }

    public function testSubscriberArrayRemovedWhenEmptyToAvoidLeakage() {
        $mediator = new Mediator;

        Loop::run(function () use ($mediator) {
            $mediator->subscribe("event.foo", function () {});
            $mediator->subscribe("event.foo", function ($m, $id) { $m->unsubscribe($id); });
            $mediator->subscribe("event.bar", function () {});
            $mediator->subscribe("event.baz", function ($m, $id) { $m->unsubscribe($id); });
            yield $mediator->publish("event.foo", 42);
            yield $mediator->publish("event.bar", 42);
            yield $mediator->publish("event.baz", 42);
        });

        $eventMap = (function() { return $this->eventSubscriberMap; })->call($mediator);
        $this->assertFalse(isset($eventMap["event.baz"]));
    }
}
