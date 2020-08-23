#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use Amp\PipelineSource;
use function Amp\asyncCall;

Loop::run(function () {
    try {
        /** @psalm-var PipelineSource<int> $source */
        $source = new PipelineSource;
        $pipeline = $source->pipe();

        asyncCall(function (PipelineSource $source): \Generator {
            yield $source->emit(yield new Delayed(500, 1));
            yield $source->emit(yield new Delayed(1500, 2));
            yield $source->emit(yield new Delayed(1000, 3));
            yield $source->emit(yield new Delayed(2000, 4));
            yield $source->emit(5);
            yield $source->emit(6);
            yield $source->emit(7);
            yield $source->emit(yield new Delayed(2000, 8));
            yield $source->emit(9);
            yield $source->emit(10);
            $source->complete();
        }, $source);

        while (null !== $value = yield $pipeline->continue()) {
            \printf("Pipeline source yielded %d\n", $value);
            yield new Delayed(500); // Listener consumption takes 500 ms.
        }
    } catch (\Exception $exception) {
        \printf("Exception: %s\n", (string) $exception);
    }
});
