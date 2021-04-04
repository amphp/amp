#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\PipelineSource;
use function Revolt\EventLoop\defer;
use function Revolt\EventLoop\delay;

try {
    /** @psalm-var PipelineSource<int> $source */
    $source = new PipelineSource;
    $pipeline = $source->pipe();

    defer(function (PipelineSource $source): void {
        delay(500);
        $source->yield(1);
        delay(1500);
        $source->yield(2);
        delay(1000);
        $source->yield(3);
        delay(2000);
        $source->yield(4);
        $source->yield(5);
        $source->yield(6);
        $source->yield(7);
        delay(2000);
        $source->yield(8);
        $source->yield(9);
        $source->yield(10);
        $source->complete();
    }, $source);

    foreach ($pipeline as $value) {
        \printf("Pipeline source yielded %d\n", $value);
        delay(500); // Listener consumption takes 500 ms.
    }
} catch (\Exception $exception) {
    \printf("Exception: %s\n", (string) $exception);
}
