#!/usr/bin/env php
<?php
use Amp\Deferred;
use Amp\Loop;

require_once __DIR__ . "/../../vendor/autoload.php";

function asyncOperation() {
    $def = new Deferred();

    \Amp\Loop::delay(1000, function () use ($def) {
        throw new Exception("force exception"); //this will be NOT catch-ed
    });

    return $def->promise();
}

Loop::run(function () {
    try {
        $res = yield asyncOperation();

        echo "result -> " . $res . PHP_EOL;
    } catch (Throwable $e) {
        echo "catch -> " . $e->getMessage() . PHP_EOL;
    }
});
