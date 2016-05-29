#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Coroutine;
use Amp\Pause;
use Amp\Postponed;
use Amp\Loop\NativeLoop;
use Interop\Async\Loop;

Loop::execute(Amp\coroutine(function () {
    try {
        $postponed = new Postponed;

        $observable = $postponed->getObservable();

        $disposable = $observable->subscribe(function ($value) {
            printf("Observable emitted %d\n", $value);
            return new Pause(500); // Artificial back-pressure on observable.
        });

        $disposable->when(function ($exception, $value) {
            if ($exception) {
                printf("Observable failed: %s\n", $exception->getMessage());
                return;
            }

            printf("Observable result %d\n", $value);
        });

        $generator = function (Postponed $postponed) {
            yield $postponed->emit(new Pause(500, 1));
            yield $postponed->emit(new Pause(1500, 2));
            yield $postponed->emit(new Pause(1000, 3));
            yield $postponed->emit(new Pause(2000, 4));
            yield $postponed->emit(5);
            yield $postponed->emit(6);
            yield $postponed->emit(7);
            yield $postponed->emit(new Pause(2000, 8));
            yield $postponed->emit(9);
            yield $postponed->emit(10);
            $postponed->resolve(11);
        };

        yield new Coroutine($generator($postponed));

    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
}), $loop = new NativeLoop());
