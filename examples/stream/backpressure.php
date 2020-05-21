#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\StreamSource;
use function Amp\asyncCall;

Loop::run(function () {
    try {
        /** @psalm-var StreamSource<int> $source */
        $source = new StreamSource;
        $stream = $source->stream();

        asyncCall(function (StreamSource $source): \Generator {
            yield $source->yield(yield new Delayed(500, 1));
            yield $source->yield(yield new Delayed(1500, 2));
            yield $source->yield(yield new Delayed(1000, 3));
            yield $source->yield(yield new Delayed(2000, 4));
            yield $source->yield(5);
            yield $source->yield(6);
            yield $source->yield(7);
            yield $source->yield(yield new Delayed(2000, 8));
            yield $source->yield(9);
            yield $source->yield(10);
            $source->complete();
        }, $source);

        while (null !== $value = yield $stream->continue()) {
            \printf("Stream source yielded %d\n", $value);
            yield new Delayed(500); // Listener consumption takes 500 ms.
        }
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});
