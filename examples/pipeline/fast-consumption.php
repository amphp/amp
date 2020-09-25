#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\AsyncGenerator;
use Amp\Delayed;
use function Amp\async;
use function Amp\await;

try {
    /** @psalm-var AsyncGenerator<int, void, void> $pipeline */
    $pipeline = new AsyncGenerator(function (): \Generator {
        yield 1;
        yield await(new Delayed(500, 2));
        yield 3;
        yield await(new Delayed(300, 4));
        yield 5;
        yield 6;
        yield await(new Delayed(1000, 7));
        yield 8;
        yield 9;
        yield await(new Delayed(600, 10));
    });

    // Flow listener attempts to consume 11 values at once. Only 10 will be emitted.
    $promises = [];
    for ($i = 0; $i < 11 && ($promises[] = async(fn () => $pipeline->continue())); ++$i);

    foreach ($promises as $key => $promise) {
        if (null === $yielded = await($promise)) {
            \printf("Async generator completed after yielding %d values\n", $key);
            break;
        }

        \printf("Async generator yielded %d\n", $yielded);
    }
} catch (\Exception $exception) {
    \printf("Exception: %s\n", (string) $exception);
}

