#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Loop;

print "Press Ctrl+C to exit..." . PHP_EOL;

Loop::onSignal(SIGINT, function () {
    print "Caught SIGINT, exiting..." . PHP_EOL;
    
    // Check for a Uv driver
    if (Loop::get() instanceof Amp\Loop\UvDriver) {

        // Stop the loop
        Loop::stop();

        // Cannot exit out of a UvDriver loop here, can only stop the loop
        return;
    }
    
    exit(0);
});

Loop::run();

exit(0);
