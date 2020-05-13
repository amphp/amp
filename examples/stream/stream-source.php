#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\StreamSource;

Loop::run(function () {
    try {
        $source = new StreamSource;

        Loop::defer(function () use ($source) {
            // Source yields all values at once without awaiting back-pressure.
            $source->yield(1);
            $source->yield(2);
            $source->yield(3);
            $source->yield(4);
            $source->yield(5);
            $source->yield(6);
            $source->yield(7);
            $source->yield(8);
            $source->yield(9);
            $source->yield(10);
            $source->complete();
        });

        $stream = $source->stream();

        while (list($value, $key) = yield $stream->continue()) {
            \printf("Stream source yielded %d\n", $value);
            yield new Delayed(100); // Listener consumption takes 100 ms.
        }
    } catch (\Throwable $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});
