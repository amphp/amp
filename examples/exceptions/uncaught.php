#!/usr/bin/env php
<?php
use Amp\Deferred;
use Amp\Loop;

require_once __DIR__ . "/../../vendor/autoload.php";

function asyncOperation() {
    $def = new Deferred();

    Loop::delay(1000, function () use ($def) {
        throw new Exception("force exception");
    });

    return $def->promise();
}

try {
    Loop::run(function () {
        try {
            $res = yield asyncOperation();

            echo "asyncOperation result -> " . $res . PHP_EOL;
        } catch (Throwable $e) {
            echo "asyncOperation catch -> " . $e->getMessage() . PHP_EOL;
        }
    });
} catch (Throwable $loopException) {
    echo "loopException -> " . $loopException->getMessage() . PHP_EOL;
}
