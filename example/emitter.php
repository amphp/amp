#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Amp\Pause;
use Amp\Postponed;
use Amp\Loop\NativeLoop;
use Interop\Async\Loop;

Loop::execute(function () {
    try {
        $postponed = new Postponed;

        Loop::defer(function () use ($postponed) {
            $postponed->emit(new Pause(500, 1));
            $postponed->emit(new Pause(1500, 2));
            $postponed->emit(new Pause(1000, 3));
            $postponed->emit(new Pause(2000, 4));
            $postponed->emit(5);
            $postponed->emit(6);
            $postponed->emit(7);
            $postponed->emit(new Pause(2000, 8));
            $postponed->emit(9);
            $postponed->emit(10);
            $postponed->resolve(11);
        });

        $observable = $postponed->getObservable();

        $disposable = $observable->subscribe(function ($value) {
            printf("Observable emitted %d\n", $value);
            return new Pause(500); // Artificial back-pressure on observable, but is ignored.
        });

        $disposable->when(function ($exception, $value) {
            if ($exception) {
                printf("Exception: %s\n", $exception->getMessage());
                return;
            }

            printf("Observable result %d\n", $value);
        });
    } catch (\Exception $exception) {
        printf("Exception: %s\n", $exception);
    }
}, $loop = new NativeLoop());
