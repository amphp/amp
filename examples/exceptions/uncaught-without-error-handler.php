#!/usr/bin/env php
<?php

use Amp\Loop;

require_once __DIR__ . "/../../vendor/autoload.php";

try {
    Loop::run(function () {
        // Uncaught exceptions in loop callbacks just bubble out of Loop::run()
        Loop::delay(1000, function () {
            throw new Exception("force exception");
        });
    });

    echo "continuing normally" . PHP_EOL;
} catch (Throwable $loopException) {
    echo "loop bubbled exception caught -> " . $loopException->getMessage() . PHP_EOL;
}
