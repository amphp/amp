#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\AsyncGenerator;
use Amp\Delayed;
use Amp\Loop;

Loop::run(function () {
    try {
        /** @psalm-var AsyncGenerator<int, int, int> $generator */
        $generator = new AsyncGenerator(function (callable $yield): \Generator {
            $value = yield $yield(0);
            $value = yield $yield(yield new Delayed(500, $value));
            $value = yield $yield($value);
            $value = yield $yield(yield new Delayed(300, $value));
            $value = yield $yield($value);
            $value = yield $yield($value);
            $value = yield $yield(yield new Delayed(1000, $value));
            $value = yield $yield($value);
            $value = yield $yield($value);
            $value = yield $yield(yield new Delayed(600, $value));
            return $value;
        });

        // Use AsyncGenerator::continue() to get the first yielded value.
        if ($value = yield $generator->continue()) {
            $value = $value->unwrap();
            \printf("Async Generator yielded %d\n", $value);

            // Use AsyncGenerator::send() to send values into the generator and get the next yielded value.
            while ($value = yield $generator->send($value + 1)) {
                $value = $value->unwrap();
                \printf("Async Generator yielded %d\n", $value);
                yield new Delayed(100); // Listener consumption takes 100 ms.
            }
        }

        \printf("Async Generator returned %d\n", yield $generator->getReturn());
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});
