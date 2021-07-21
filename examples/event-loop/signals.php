#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Loop;

print "Press Ctrl+C to exit..." . PHP_EOL;

Loop::onSignal(SIGINT, function ($watcherId) {
    print "Caught SIGINT, exiting..." . PHP_EOL;
    
    Loop::cancel($watcherId);
    
    exit(0);
});

Loop::run();

exit(0);
