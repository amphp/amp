#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\Producer;

Loop::run(function () {
    try {
        $iterator = new Producer(function (callable $emit) {
            yield $emit(1);
            yield $emit(new Delayed(500, 2));
            yield $emit(3);
            yield $emit(new Delayed(300, 4));
            yield $emit(5);
            yield $emit(6);
            yield $emit(new Delayed(1000, 7));
            yield $emit(8);
            yield $emit(9);
            yield $emit(new Delayed(600, 10));
            return 11;
        });

        while (yield $iterator->advance()) {
            \printf("Producer emitted %d\n", $iterator->getCurrent());
            yield new Delayed(100); // Listener consumption takes 100 ms.
        }
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", $exception);
    }
});
