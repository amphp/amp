#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\PipelineSource;
use function Revolt\EventLoop\defer;
use function Revolt\EventLoop\delay;

try {
    /** @psalm-var PipelineSource<int> $source */
    $source = new PipelineSource;

    defer(function () use ($source): void {
        // Source emits all values at once without awaiting back-pressure.
        $source->emit(1);
        $source->emit(2);
        $source->emit(3);
        $source->emit(4);
        $source->emit(5);
        $source->emit(6);
        $source->emit(7);
        $source->emit(8);
        $source->emit(9);
        $source->emit(10);
        $source->complete();
    });

    $pipeline = $source->pipe();

    // Use Amp\Pipeline\toIterator() to use a pipeline with foreach.
    foreach ($pipeline as $value) {
        \printf("Pipeline source yielded %d\n", $value);
        delay(100); // Listener consumption takes 100 ms.
    }
} catch (\Throwable $exception) {
    \printf("Exception: %s\n", (string) $exception);
}
