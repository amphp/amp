<?php

namespace Amp\Cancellation;

use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use function Amp\delay;

class CompositeCancellationTest extends AsyncTestCase
{
    private const LOOP_COUNT = 20;
    private const TOKENS_COUNT = 1000;

    public function testBenchmark(): void
    {
        $firstMemoryMeasure = 0;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $tokens = [];
            for ($j = 0; $j < self::TOKENS_COUNT; $j++) {
                $tokens[] = (new DeferredCancellation())->getCancellation();
            }
            $combinedToken = new CompositeCancellation(...$tokens);

            if (!$firstMemoryMeasure && $i > self::LOOP_COUNT / 2) {
                // Warmup and store first memory usage after 50% of iterations
                $firstMemoryMeasure = \memory_get_usage(true);
            }
            // Remove tokens from memory
            unset($combinedToken);

            delay(0.001); // Tick loop to allow resources to be freed.

            // Asserts
            if ($firstMemoryMeasure > 0) {
                self::assertLessThanOrEqual($firstMemoryMeasure, \memory_get_usage(true));
            }
        }
    }

    public function testCombinedWithDoubleCancellation(): void
    {
        $deferredCancellation1 = new DeferredCancellation();
        $deferredCancellation2 = new DeferredCancellation();

        $compositeCancellation = new CompositeCancellation(
            $deferredCancellation1->getCancellation(),
            $deferredCancellation2->getCancellation(),
        );

        $compositeCancellation->subscribe(
            $this->createCallback(1, expectArgs: [self::isInstanceOf(CancelledException::class)])
        );

        $deferredCancellation1->cancel($exception = new TestException());
        $deferredCancellation2->cancel(new TestException());

        delay(0.1); // Ensure cancellation callbacks are invoked.

        // Add another cancellation subscriber to ensure exception did not change.
        $compositeCancellation->subscribe(function (CancelledException $cancelled) use ($exception) {
            self::assertSame($exception, $cancelled->getPrevious());
        });
    }
}
