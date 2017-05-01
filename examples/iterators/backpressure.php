#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Coroutine;
use Amp\Emitter;
use Amp\Loop;
use Amp\Pause;
use Amp\Promise;

Loop::run(function () {
    try {
        $emitter = new Emitter;
        $iterator = $emitter->iterate();

        $generator = function (Emitter $emitter) {
            yield $emitter->emit(new Pause(500, 1));
            yield $emitter->emit(new Pause(1500, 2));
            yield $emitter->emit(new Pause(1000, 3));
            yield $emitter->emit(new Pause(2000, 4));
            yield $emitter->emit(5);
            yield $emitter->emit(6);
            yield $emitter->emit(7);
            yield $emitter->emit(new Pause(2000, 8));
            yield $emitter->emit(9);
            yield $emitter->emit(10);
            $emitter->complete();
        };

        Promise\rethrow(new Coroutine($generator($emitter)));

        while (yield $iterator->advance()) {
            printf("Emitter emitted %d\n", $iterator->getCurrent());
            yield new Pause(500); // Listener consumption takes 500 ms.
        }
    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
});
