#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Coroutine;
use Amp\Observable;
use Amp\Observer;
use Amp\Pause;
use Amp\Postponed;
use Amp\Loop\NativeLoop;
use Interop\Async\Loop;

Loop::execute(Amp\coroutine(function () {
    try {
        $postponed = new Postponed;

        Loop::defer(function () use ($postponed) {
            // Observer emits all values at once.
            $postponed->emit(1);
            $postponed->emit(2);
            $postponed->emit(3);
            $postponed->emit(4);
            $postponed->emit(5);
            $postponed->emit(6);
            $postponed->emit(7);
            $postponed->emit(8);
            $postponed->emit(9);
            $postponed->emit(10);
            $postponed->resolve(11);
        });

        $observable = $postponed->getObservable();

        $generator = function (Observable $observable) {
            $observer = new Observer($observable);

            while (yield $observer->next()) {
                printf("Observable emitted %d\n", $observer->getCurrent());
                yield new Pause(100); // Observer consumption takes 100 ms.
            }

            printf("Observable result %d\n", $observer->getResult());
        };

        yield new Coroutine($generator($observable));

    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
}), $loop = new NativeLoop());
