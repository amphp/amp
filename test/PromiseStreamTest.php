<?php

namespace Amp\Test;

use Amp\PromiseStream;
use Amp\NativeReactor;
use Amp\Deferred;

class PromiseStreamTest extends \PHPUnit_Framework_TestCase {

    public function testStream() {
        $endReached = false;
        (new NativeReactor)->run(function($reactor) use (&$endReached) {
            $def = new Deferred;
            $msg = new PromiseStream($def->promise());
            $i = 0;
            $reactor->repeat(function($reactor, $watcherId) use ($def, &$i) {
                $i++;
                $def->update("test{$i}");
                if ($i === 3) {
                    $def->succeed();
                    $reactor->cancel($watcherId);
                }
            }, 100);

            $results = [];
            foreach ($msg->stream() as $msgElement) {
                $results[] = (yield $msgElement);
            }

            $this->assertSame(["test1", "test2", "test3", null], $results);
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }

    public function testStreamRetainsUpdatesUntilInitialized() {
        $endReached = false;
        (new NativeReactor)->run(function($reactor) use (&$endReached) {
            $def = new Deferred;
            $msg = new PromiseStream($def->promise());
            $def->update("foo");
            $def->update("bar");
            $def->update("baz");
            $def->succeed();
            
            $results = [];
            foreach ($msg->stream() as $msgElement) {
                $results[] = (yield $msgElement);
            }
            $this->assertSame(["foo", "bar", "baz", null], $results);
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage test
     */
    public function testStreamThrowsIfPromiseFails() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new PromisorPrivateImpl;
            $reactor->repeat(function($reactor, $watcherId) use (&$i, $promisor) {
                $i++;
                $promisor->update($i);
                if ($i === 3) {
                    $reactor->cancel($watcherId);
                    $promisor->fail(new \Exception(
                        "test"
                    ));
                }
            }, 10);

            $msg = new PromiseStream($promisor->promise());
            
            $results = [];
            foreach ($msg->stream() as $msgElement) {
                $results[] = (yield $msgElement);
            }
        });
    }
}
