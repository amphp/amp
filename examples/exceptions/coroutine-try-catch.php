#!/usr/bin/env php
<?php

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

require_once __DIR__ . "/../../vendor/autoload.php";

/**
 * @return Promise<string>
 */
function asyncOperation(): Promise
{
    $def = new Deferred();

    Loop::delay(1000, function () use ($def) {
        $def->fail(new Exception("force exception"));
    });

    return $def->promise();
}

try {
    Loop::run(function () {
        try {
            // the failing promise returned from asyncOperation() will throw at the point of yield
            // and can be caught like any other exception in PHP.
            $res = yield asyncOperation();

            echo "asyncOperation result -> " . $res . PHP_EOL;
        } catch (Throwable $e) {
            echo "asyncOperation catch -> " . $e->getMessage() . PHP_EOL;
        }
    });
} catch (Throwable $loopException) {
    echo "loop bubbled exception caught -> " . $loopException->getMessage() . PHP_EOL;
}
