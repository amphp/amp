#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Coroutine;
use Amp\Emitter;
use Amp\Listener;
use Amp\Pause;
use Amp\Stream;
use Amp\Loop;

Loop::run(function () {
    try {
        $emitter = new Emitter;

        Loop::defer(function () use ($emitter) {
            // Listener emits all values at once.
            $emitter->emit(1);
            $emitter->emit(2);
            $emitter->emit(3);
            $emitter->emit(4);
            $emitter->emit(5);
            $emitter->emit(6);
            $emitter->emit(7);
            $emitter->emit(8);
            $emitter->emit(9);
            $emitter->emit(10);
            $emitter->resolve(11);
        });

        $stream = $emitter->stream();

        $generator = function (Stream $stream) {
            $listener = new Listener($stream);

            while (yield $listener->advance()) {
                printf("Stream emitted %d\n", $listener->getCurrent());
                yield new Pause(100); // Listener consumption takes 100 ms.
            }

            printf("Stream result %d\n", $listener->getResult());
        };

        yield new Coroutine($generator($stream));
    } catch (\Throwable $exception) {
        printf("Exception: %s\n", $exception);
    }
});