<?php

use function Amp\delay;

require __DIR__ . '/../vendor/autoload.php';

Amp\async(function () {
    print '++ Executing callback passed to async()' . PHP_EOL;

    delay(3);

    print '++ Finished callback passed to async()' . PHP_EOL;
});

print '++ Suspending to event loop...' . PHP_EOL;
delay(5);

print '++ Script end' . PHP_EOL;
