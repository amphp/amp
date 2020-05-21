#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\AsyncGenerator;
use Amp\Delayed;
use Amp\Loop;

Loop::run(function () {
    try {
        /** @psalm-var AsyncGenerator<int, void, void> $stream */
        $stream = new AsyncGenerator(function (callable $yield): \Generator {
            yield $yield(1);
            yield $yield(yield new Delayed(500, 2));
            yield $yield(3);
            yield $yield(yield new Delayed(300, 4));
            yield $yield(5);
            yield $yield(6);
            yield $yield(yield new Delayed(1000, 7));
            yield $yield(8);
            yield $yield(9);
            yield $yield(yield new Delayed(600, 10));
        });

        // Flow listener attempts to consume 11 values at once. Only 10 will be yielded.
        $promises = [];
        for ($i = 0; $i < 11 && ($promises[] = $stream->continue()); ++$i);

        foreach ($promises as $key => $promise) {
            if (null === $yielded = yield $promise) {
                \printf("Async generator completed after yielding %d values\n", $key);
                break;
            }

            \printf("Async generator yielded %d\n", $yielded);
        }
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});
