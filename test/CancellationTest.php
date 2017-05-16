<?php

namespace Amp\Test;

use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\Emitter;
use Amp\Loop;
use PHPUnit\Framework\TestCase;
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
}
