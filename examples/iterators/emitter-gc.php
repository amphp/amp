#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Emitter;
use Amp\Loop;

Loop::run(function () {
    $emitter = new Emitter;

    Loop::defer(function () use ($emitter) {
        try {
            yield $emitter->emit(1);
            print "Emit done.\n";
            yield $emitter->emit(2);
            print "Never reached...\n";
            $emitter->complete();
        } finally {
            print "Garbage collected...\n";
        }
    });

    $iterator = $emitter->iterate();
    yield $iterator->advance();
    yield $iterator->advance();
    yield new Amp\Delayed(0);

    unset($emitter, $iterator);
    gc_collect_cycles();

    print "Done.\n";
});
