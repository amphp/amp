#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\PipelineSource;
use Amp\Promise;
use function Amp\async;
use function Amp\await;
use function Amp\sleep;

try {
    /** @psalm-var PipelineSource<int> $source */
    $source = new PipelineSource;
    $pipeline = $source->pipe();

    Promise\rethrow(async(function (PipelineSource $source): void {
        await($source->emit(await(new Delayed(500, 1))));
        await($source->emit(await(new Delayed(1500, 2))));
        await($source->emit(await(new Delayed(1000, 3))));
        await($source->emit(await(new Delayed(2000, 4))));
        await($source->emit(5));
        await($source->emit(6));
        await($source->emit(7));
        await($source->emit(await(new Delayed(2000, 8))));
        await($source->emit(9));
        await($source->emit(10));
        $source->complete();
    }, $source));

    while (null !== $value = $pipeline->continue()) {
        \printf("Pipeline source yielded %d\n", $value);
        sleep(500); // Listener consumption takes 500 ms.
    }
} catch (\Exception $exception) {
    \printf("Exception: %s\n", (string) $exception);
}
