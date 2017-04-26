#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Emitter;
use Amp\Loop;
use Amp\Stream;
use Amp\StreamIterator;

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

        while (yield $stream->advance()) {
            printf("Stream emitted %d\n", $stream->getCurrent());
            yield new Delayed(100); // Listener consumption takes 100 ms.
        }
    } catch (\Throwable $exception) {
        printf("Exception: %s\n", $exception);
    }
});
