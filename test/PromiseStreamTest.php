<?php

namespace Amp\Test;

class PromiseStreamTest extends BaseTest {
    public function testStream() {
        $endReached = false;
        \Amp\run(function () use (&$endReached) {
            $promisor = new \Amp\Deferred;
            $stream = new \Amp\PromiseStream($promisor->promise());
            $i = 0;
            \Amp\repeat(function ($watcherId) use ($promisor, &$i) {
                $i++;
                $promisor->update("test{$i}");
                if ($i === 3) {
                    $promisor->succeed();
                    \Amp\cancel($watcherId);
                }
            }, 10);

            $results = [];
            while (yield $stream->valid()) {
                $results[] = $stream->consume();
            }

            $this->assertSame(["test1", "test2", "test3"], $results);
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }

    public function testStreamReturnsPromiseResolutionForFirstConsumeCallAfterSuccess() {
        $endReached = false;
        \Amp\run(function () use (&$endReached) {
            $promisor = new \Amp\Deferred;
            $stream = new \Amp\PromiseStream($promisor->promise());
            $i = 0;
            \Amp\repeat(function ($watcherId) use ($promisor, &$i) {
                $i++;
                $promisor->update("test{$i}");
                if ($i === 3) {
                    $promisor->succeed(42);
                    \Amp\cancel($watcherId);
                }
            }, 10);

            $results = [];
            while (yield $stream->valid()) {
                $stream->consume();
            }

            $this->assertSame(42, $stream->consume());
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }

    public function testStreamRetainsUpdatesUntilInitialized() {
        $endReached = false;
        \Amp\run(function () use (&$endReached) {
            $promisor = new \Amp\Deferred;
            $stream = new \Amp\PromiseStream($promisor->promise());
            $promisor->update("foo");
            $promisor->update("bar");
            $promisor->update("baz");
            $promisor->succeed();

            $results = [];
            while (yield $stream->valid()) {
                $results[] = $stream->consume();
            }
            $endReached = true;
            $this->assertSame(["foo", "bar", "baz"], $results);
        });
        $this->assertTrue($endReached);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage test
     */
    public function testStreamThrowsIfPromiseFails() {
        \Amp\run(function () {
            $i = 0;
            $promisor = new \Amp\Deferred;
            \Amp\repeat(function ($watcherId) use (&$i, $promisor) {
                $i++;
                $promisor->update($i);
                if ($i === 2) {
                    \Amp\cancel($watcherId);
                    $promisor->fail(new \Exception(
                        "test"
                    ));
                }
            }, 10);

            $stream = new \Amp\PromiseStream($promisor->promise());

            $results = [];
            while (yield $stream->valid()) {
                $results[] = $stream->consume();
            }
        });
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot advance PromiseStream beyond unresolved index 0
     */
    public function testPrematureConsumptionThrows() {
        \Amp\run(function () {
            $promisor = new \Amp\Deferred;
            $stream = new \Amp\PromiseStream($promisor->promise());
            $results = [];
            $stream->consume();
        });
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot advance PromiseStream beyond completed index 1
     */
    public function testConsumeAfterStreamCompletionThrows() {
        \Amp\run(function () {
            $promisor = new \Amp\Deferred;
            $promisor->update(0);
            $promisor->succeed(1);
            $stream = new \Amp\PromiseStream($promisor->promise());
            $results = [];
            $stream->consume();
            $stream->consume();
            $stream->consume();
        });
    }
}
