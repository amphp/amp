#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Interop\Async\Loop;
use Amp\Pause;
use Amp\Emitter;
use Amp\Loop\NativeLoop;

Loop::execute(Amp\coroutine(function () {
    try {
        $observable = new Emitter(function (callable $emit) {
            yield $emit(new Pause(500, 1));
            yield $emit(new Pause(1500, 2));
            yield $emit(new Pause(1000, 3));
            yield $emit(new Pause(1000, 4));
            yield $emit(5); // The values starting here will be emitted in 0.5 second intervals because the coroutine
            yield $emit(6); // consuming values below takes 0.5 seconds per iteration. This behavior occurs because
            yield $emit(7); // observables respect back-pressure from consumers, waiting to emit a value until all
            yield $emit(8); // consumers have finished processing (if desired, see the docs on using and avoiding
            yield $emit(9); // back-pressure).
            yield $emit(10);
        });

        $iterator = $observable->getIterator();

        while (yield $iterator->isValid()) {
            printf("Observable emitted %d\n", $iterator->getCurrent());
            yield new Pause(500); // Artificial back-pressure on observable.
        }

    } catch (\Throwable $exception) {
        printf("Exception: %s\n", $exception);
    }
}), $loop = new NativeLoop());
