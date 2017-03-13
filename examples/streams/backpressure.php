#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Coroutine;
use Amp\Emitter;
use Amp\Pause;
use Amp\Loop;

Loop::run(function () {
    try {
        $emitter = new Emitter;

        $stream = $emitter->stream();

        $stream->listen(function ($value) {
            printf("Stream emitted %d\n", $value);
            return new Pause(500); // Artificial back-pressure on stream.
        });

        $stream->when(function (Throwable $exception = null, $value) {
            if ($exception) {
                printf("Stream failed: %s\n", $exception->getMessage());
                return;
            }

            printf("Stream result %d\n", $value);
        });

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
            $emitter->resolve(11);
        };

        yield new Coroutine($generator($emitter));
    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
});