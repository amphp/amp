#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\AsyncGenerator;
use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\delay;
use function Revolt\Future\spawn;

$future = spawn(function (): void {
    try {
        $timer = Loop::repeat(100, function () {
            echo ".", PHP_EOL; // This repeat timer is to show the loop is not being blocked.
        });
        Loop::unreference($timer); // Unreference timer so the loop exits automatically when all tasks complete.

        /** @psalm-var AsyncGenerator<int, null, null> $pipeline */
        $pipeline = new AsyncGenerator(function (): \Generator {
            yield 1;
            delay(500);
            yield 2;
            yield 3;
            delay(300);
            yield 4;
            yield 5;
            yield 6;
            delay(1000);
            yield 7;
            yield 8;
            yield 9;
            delay(600);
            yield 10;
        });

        echo "Unpacking AsyncGenerator, please wait...\n";
        \var_dump(...$pipeline);
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});

$future->join();
