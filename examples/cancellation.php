<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\CancelledException;
use Amp\TimeoutCancellation;
use function Amp\async;
use function Amp\delay;

$future = async(function () {
    delay(1);

    print 'Operation complete.' . PHP_EOL;
});

try {
    // This won't cancel the actual operation, but just the await operation
    $future->await(new TimeoutCancellation(0.5));
} catch (CancelledException) {
    echo 'Await operation has been cancelled.' . PHP_EOL;
}

// We can still await again at a later point in time
$future->await();
