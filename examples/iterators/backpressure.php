#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Emitter;
use Amp\Loop;
use function Amp\asyncCall;

Loop::run(function () {
    try {
        $emitter = new Emitter;
        $iterator = $emitter->iterate();

        $generator = function (Emitter $emitter) {
            yield $emitter->emit(new Delayed(500, 1));
            yield $emitter->emit(new Delayed(1500, 2));
            yield $emitter->emit(new Delayed(1000, 3));
            yield $emitter->emit(new Delayed(2000, 4));
            yield $emitter->emit(5);
            yield $emitter->emit(6);
            yield $emitter->emit(7);
            yield $emitter->emit(new Delayed(2000, 8));
            yield $emitter->emit(9);
            yield $emitter->emit(10);
            $emitter->complete();
        };

        asyncCall($generator, $emitter);

        while (yield $iterator->advance()) {
            printf("Emitter emitted %d\n", $iterator->getCurrent());
            yield new Delayed(500); // Listener consumption takes 500 ms.
        }
    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
});
