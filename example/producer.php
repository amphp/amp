#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\{ Coroutine, Listener, Pause, Producer, Stream };
use Interop\Async\Loop;

Loop::execute(Amp\wrap(function () {
    try {
        $producer = new Producer(function (callable $emit) {
            yield $emit(1);
            yield $emit(new Pause(500, 2));
            yield $emit(3);
            yield $emit(new Pause(300, 4));
            yield $emit(5);
            yield $emit(6);
            yield $emit(new Pause(1000, 7));
            yield $emit(8);
            yield $emit(9);
            yield $emit(new Pause(600, 10));
            return 11;
        });

        $generator = function (Stream $stream) {
            $listener = new Listener($stream);

            while (yield $listener->advance()) {
                printf("Stream emitted %d\n", $listener->getCurrent());
                yield new Pause(100); // Listener consumption takes 100 ms.
            }

            printf("Stream result %d\n", $listener->getResult());
        };

        yield new Coroutine($generator($producer));

    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
}));
