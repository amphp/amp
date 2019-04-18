#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use function Amp\asyncCall;

// Shows how two for loops are executed concurrently.

// Note that the first two items are printed _before_ the Loop::run()
// as they're executed immediately and do not register any timers or defers.

asyncCall(function () {
    for ($i = 0; $i < 5; $i++) {
        print "1 - " . $i . PHP_EOL;
        yield new Delayed(1000);
    }
});

asyncCall(function () {
    for ($i = 0; $i < 5; $i++) {
        print "2 - " . $i . PHP_EOL;
        yield new Delayed(400);
    }
});

print "-- before Loop::run()" . PHP_EOL;

Loop::run();

print "-- after Loop::run()" . PHP_EOL;
