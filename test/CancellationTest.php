<?php

namespace Amp\Test;

use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\Emitter;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Amp\Success;
use function Amp\asyncCall;

class CancellationTest extends TestCase {
    private function createAsyncIterator(CancellationToken $cancellationToken) {
        $emitter = new Emitter;

        asyncCall(function () use ($emitter, $cancellationToken) {
            $running = true;

            $cancellationToken->subscribe(function () use (&$running) {
                $running = false;
            });

            $i = 0;

            while ($running) {
                yield $emitter->emit($i++);
            }
        });

        return $emitter->iterate();
    }

    public function testCancellationCancelsIterator() {
        Loop::run(function () {
            $cancellationSource = new CancellationTokenSource;

            $iterator = $this->createAsyncIterator($cancellationSource->getToken());

            $current = null;

            while (yield $iterator->advance()) {
                $current = $iterator->getCurrent();

                $this->assertInternalType("int", $current);

                if ($current === 3) {
                    $cancellationSource->cancel();
                }
            }

            $this->assertSame(3, $current);
        });
    }

    public function testUnsubscribeWorks() {
        Loop::run(function () {
            $cancellationSource = new CancellationTokenSource;

            $id = $cancellationSource->getToken()->subscribe(function () {
                $this->fail("Callback has been called");
            });

            $cancellationSource->getToken()->subscribe(function () {
                $this->assertTrue(true);
            });

            $cancellationSource->getToken()->unsubscribe($id);

            $cancellationSource->cancel();
        });
    }

    public function testSubscriptionsRunAsCoroutine() {
        $this->expectOutputString("abc");

        Loop::run(function () {
            $cancellationSource = new CancellationTokenSource;
            $cancellationSource->getToken()->subscribe(function () {
                print yield new Success("a");
                print yield new Success("b");
                print yield new Success("c");
            });

            $cancellationSource->cancel();
        });
    }

    public function testThrowingCallbacksEndUpInLoop() {
        Loop::run(function () {
            $this->expectException(TestException::class);

            $cancellationSource = new CancellationTokenSource;
            $cancellationSource->getToken()->subscribe(function () {
                throw new TestException;
            });

            try {
                $cancellationSource->cancel();
            } catch (TestException $e) {
                $this->fail("Exception thrown from cancel instead of being thrown into the loop.");
            }
        });
    }

    public function testThrowingCallbacksEndUpInLoopIfCoroutine() {
        Loop::run(function () {
            $this->expectException(TestException::class);

            $cancellationSource = new CancellationTokenSource;
            $cancellationSource->getToken()->subscribe(function () {
                if (false) {
                    yield;
                }

                throw new TestException;
            });

            try {
                $cancellationSource->cancel();
            } catch (TestException $e) {
                $this->fail("Exception thrown from cancel instead of being thrown into the loop.");
            }
        });
    }

    public function testDoubleCancelOnlyInvokesOnce() {
        Loop::run(function () {
            $cancellationSource = new CancellationTokenSource;
            $cancellationSource->getToken()->subscribe($this->createCallback(1));

            $cancellationSource->cancel();
            $cancellationSource->cancel();
        });
    }

    public function testCalledIfSubscribingAfterCancel() {
        Loop::run(function () {
            $cancellationSource = new CancellationTokenSource;
            $cancellationSource->cancel();
            $cancellationSource->getToken()->subscribe($this->createCallback(1));
        });
    }
}
