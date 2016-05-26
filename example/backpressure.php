#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Coroutine;
use Amp\Observable;
use Amp\Pause;
use Amp\Postponed;
use Amp\Loop\NativeLoop;
use Interop\Async\Loop;

Loop::execute(Amp\coroutine(function () {
    try {
        $coroutines = [];

        $postponed = new Postponed;

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
            yield $postponed->complete(11);
        };

        $coroutines[] = new Coroutine($generator($postponed));

        $generator = function (Observable $observable) {
            $observer = $observable->getObserver();

            while (yield $observer->isValid()) {
                printf("Observable emitted %d\n", $observer->getCurrent());
                yield new Pause(500); // Artificial back-pressure on observer.
            }

            printf("Observable result %d\n", $observer->getReturn());
        };


        $coroutines[] = new Coroutine($generator($postponed->getObservable()));

        yield Amp\all($coroutines);

    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
}), $loop = new NativeLoop());
