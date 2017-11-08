#!/usr/bin/env php
<?php

use Amp\Deferred;
use Amp\Loop;

require_once __DIR__ . "/../../vendor/autoload.php";

/**
 * @return \Amp\Promise<string>
 */
function jobSuccess() {
    $deferred = new Deferred();

    //we delay Promise resolve for 1 sec to simulate some async job
    Loop::delay( 1 * 1000, function () use ($deferred) {
        $deferred->resolve("value");
    });

    return $deferred->promise();
}

/**
 * @return \Amp\Promise<string>
 */
function jobFail() {
    $deferred = new Deferred();

    //we delay Promise fail for 2 sec to simulate some async job
    Loop::delay( 2 * 1000, function () use ($deferred) {
        $deferred->fail(new Exception("force fail"));
    });

    return $deferred->promise();
}

//remark: onResolve() shouldn't be used directly in 99% of all cases
//check https://github.com/amphp/amp/issues/178#issuecomment-342460585
//check deferred.php for a cleaner code syntax
Loop::run(function () {
    jobSuccess()->onResolve(function (Throwable $error = null, $result = null) {
        if ($error) {
            echo "asyncOperation1 fail -> " . $error->getMessage() . PHP_EOL;
        } else {
            echo "asyncOperation1 result -> " . $result . PHP_EOL;
        }

        jobFail()->onResolve(function (Throwable $error = null, $result = null) {
            if ($error) {
                echo "asyncOperation2 fail -> " . $error->getMessage() . PHP_EOL;
            } else {
                echo "asyncOperation2 result -> " . $result . PHP_EOL;
            }
        });
    });
});
