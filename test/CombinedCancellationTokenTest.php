<?php
declare(strict_types=1);

namespace Amp\Test;

use Amp\CancellationTokenSource;
use Amp\CombinedCancellationToken;

class CombinedCancellationTokenTest extends BaseTest
{
    private const LOOP_COUNT = 20;
    private const TOKENS_COUNT = 1000;

    public function testBenchmark(): void
    {
        $firstMemoryMeasure = 0;

        for ($i = 0; $i < self::LOOP_COUNT; $i++) {
            $tokens = [];
            for ($j = 0; $j < self::TOKENS_COUNT; $j++) {
                $tokens[] = (new CancellationTokenSource)->getToken();
            }
            $combinedToken = new CombinedCancellationToken(...$tokens);

            if (!$firstMemoryMeasure && $i > self::LOOP_COUNT / 2) {
                // Warmup and store first memory usage after 50% of iterations
                $firstMemoryMeasure = memory_get_usage(true);
            }
            // Remove tokens from memory
            unset($combinedToken);

            // Asserts
            if ($firstMemoryMeasure > 0) {
                self::assertEquals($firstMemoryMeasure, memory_get_usage(true));
            }
            print "Memory: " . (memory_get_usage(true) / 1000) . PHP_EOL;
        }
    }
}
