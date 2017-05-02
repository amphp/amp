#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Loop;

print "Press Ctrl+C to exit..." . PHP_EOL;

Loop::onSignal(SIGINT, function () {
    print "Caught SIGINT, exiting..." . PHP_EOL;
    exit(0);
});

Loop::run();
