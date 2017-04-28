#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Emitter;
use Amp\Loop;

Loop::run(function () {
    try {
        $emitter = new Emitter;

        $stream = $emitter->stream();

        $stream->onEmit(function ($value) {
            printf("Stream emitted %d\n", $value);
            return new Delayed(500); // Artificial back-pressure on stream.
        });

        $stream->onResolve(function (Throwable $exception = null, $value) {
            if ($exception) {
                printf("Stream failed: %s\n", $exception->getMessage());
                return;
            }

            printf("Stream result %d\n", $value);
        });

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
            $emitter->resolve(11);
        };

        yield new Coroutine($generator($emitter));
    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
});
