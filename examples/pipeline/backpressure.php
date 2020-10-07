#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\PipelineSource;
use Amp\Promise;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

try {
    /** @psalm-var PipelineSource<int> $source */
    $source = new PipelineSource;
    $pipeline = $source->pipe();

    Promise\rethrow(async(function (PipelineSource $source): void {
        $source->yield(await(new Delayed(500, 1)));
        $source->yield(await(new Delayed(1500, 2)));
        $source->yield(await(new Delayed(1000, 3)));
        $source->yield(await(new Delayed(2000, 4)));
        $source->yield(5);
        $source->yield(6);
        $source->yield(7);
        $source->yield(await(new Delayed(2000, 8)));
        $source->yield(9);
        $source->yield(10);
        $source->complete();
    }, $source));

    while (null !== $value = $pipeline->continue()) {
        \printf("Pipeline source yielded %d\n", $value);
        delay(500); // Listener consumption takes 500 ms.
    }
} catch (\Exception $exception) {
    \printf("Exception: %s\n", (string) $exception);
}
