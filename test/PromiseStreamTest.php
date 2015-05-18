<?php

namespace Amp\Test;

use Amp\PromiseStream;
use Amp\NativeReactor;
use Amp\PrivateFuture;

class PromiseStreamTest extends \PHPUnit_Framework_TestCase {

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage test
     */
    public function testStreamThrowsIfPromiseFails() {
        (new NativeReactor)->run(function($reactor) {
            $promisor = new PrivateFuture;
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

            $result = (yield new PromiseStream($promisor->promise()));
        });
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot advance stream: previous Promise not yet resolved
     */
    public function testStreamThrowsIfPrematurelyIterated() {
        $promisor = new PrivateFuture;
        $stream = (new PromiseStream($promisor->promise()))->stream();
        $stream->next();
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Cannot advance stream: subject Promise failed
     */
    public function testStreamThrowsIfIteratedAfterFailure() {
        $promisor = new PrivateFuture;
        $promisor->fail(new \Exception("test"));
        $stream = (new PromiseStream($promisor->promise()))->stream();
        $stream->next();
    }
}
