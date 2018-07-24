<?php

namespace Amp\Test;

use Amp\Cancellation\Token;
use Amp\Cancellation\TokenSource;
use Amp\Emitter;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Concurrent\Task;
use function Amp\rethrow;

class CancellationTest extends TestCase
{
    private function createIterator(Token $token): \Iterator
    {
        $emitter = new Emitter;

        rethrow(Task::async(function () use ($emitter, $token) {
            $running = true;

            $token->subscribe(function () use (&$running) {
                $running = false;
            });

            $i = 0;
            while ($running) {
                $emitter->emit($i++);
            }

            $emitter->complete();
        }));

        return $emitter->extractIterator();
    }

    public function testCancellationCancelsIterator(): void
    {
        $source = new TokenSource;
        $current = null;

        foreach ($this->createIterator($source->getToken()) as $current) {
            $this->assertInternalType("int", $current);

            if ($current === 3) {
                $source->cancel();
            }
        }

        $this->assertSame(3, $current);
    }

    public function testUnsubscribeWorks(): void
    {
        $cancellationSource = new TokenSource;

        $first = $cancellationSource->getToken()->subscribe(function () {
            $this->fail("Callback has been called");
        });

        $cancellationSource->getToken()->subscribe(function () {
            $this->assertTrue(true);
        });

        $cancellationSource->getToken()->unsubscribe($first);

        $cancellationSource->cancel();
    }

    public function testThrowingCallback(): void
    {
        $this->expectException(TestException::class);

        $cancellationSource = new TokenSource;
        $cancellationSource->getToken()->subscribe(function () {
            throw new TestException(__LINE__);
        });

        $cancellationSource->cancel();
    }

    public function testDoubleCancelOnlyInvokesOnce(): void
    {
        $cancellationSource = new TokenSource;
        $cancellationSource->getToken()->subscribe($this->createCallback(1));

        $cancellationSource->cancel();
        $cancellationSource->cancel();
    }

    public function testCalledIfSubscribingAfterCancel(): void
    {
        $cancellationSource = new TokenSource;
        $cancellationSource->cancel();
        $cancellationSource->getToken()->subscribe($this->createCallback(1));
    }
}
