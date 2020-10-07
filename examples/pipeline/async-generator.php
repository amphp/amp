#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\AsyncGenerator;
use Amp\Delayed;
use function Amp\await;
use function Amp\delay;

try {
    /** @psalm-var AsyncGenerator<int, int, int> $generator */
    $generator = new AsyncGenerator(function (): \Generator {
        $value = yield 0;
        $value = yield await(new Delayed(500, $value));
        $value = yield $value;
        $value = yield await(new Delayed(300, $value));
        $value = yield $value;
        $value = yield $value;
        $value = yield await(new Delayed(1000, $value));
        $value = yield $value;
        $value = yield $value;
        $value = yield await(new Delayed(600, $value));
        return $value;
    });

    // Use AsyncGenerator::continue() to get the first emitted value.
    if (null !== $value = $generator->continue()) {
        \printf("Async Generator yielded %d\n", $value);

        // Use AsyncGenerator::send() to send values into the generator and get the next emitted value.
        while (null !== $value = $generator->send($value + 1)) {
            \printf("Async Generator yielded %d\n", $value);
            delay(100); // Listener consumption takes 100 ms.
        }
    }

    \printf("Async Generator returned %d\n", $generator->getReturn());
} catch (\Exception $exception) {
    \printf("Exception: %s\n", (string) $exception);
}
