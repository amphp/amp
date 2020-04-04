#!/usr/bin/env php
<?php

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;

require_once __DIR__ . "/../../vendor/autoload.php";

/**
 * @return Promise<string>
 */
function jobSuccess()
{
    $deferred = new Deferred();

    // We delay Promise resolve for 1 sec to simulate some async job
    Loop::delay(1 * 1000, function () use ($deferred) {
        $deferred->resolve("value");
    });

    return $deferred->promise();
}

/**
 * @return Promise<string>
 */
function jobFail()
{
    $deferred = new Deferred();

    // We delay Promise fail for 2 sec to simulate some async job
    Loop::delay(2 * 1000, function () use ($deferred) {
        $deferred->fail(new Exception("force fail"));
    });

    return $deferred->promise();
}

Loop::run(function () {
    try {
        $asyncOperation1 = yield jobSuccess();
        echo "asyncOperation1 result -> " . $asyncOperation1 . PHP_EOL;

        // jobFail() will start only after jobSuccess() is finished, all this will be executed asynchronous
        $asyncOperation2 = yield jobFail();
        echo "asyncOperation2 result -> " . $asyncOperation2 . PHP_EOL; //this statment will not run
    } catch (Throwable $exception) {
        echo "asyncOperation catch -> " . $exception->getMessage() . PHP_EOL;
    }
});
