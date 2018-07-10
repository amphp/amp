<?php

require __DIR__ . '/../../vendor/autoload.php';

use Concurrent\Task;
use function Amp\delay;

// Shows how two for loops are executed concurrently.

// Note that the first two items are printed _before_ the Loop::run()
// as they're executed immediately and do not register any timers or defers.

print "starting first task" . PHP_EOL;
$a = Task::async(function () {
    for ($i = 0; $i < 5; $i++) {
        print "1 - " . $i . PHP_EOL;
        delay(1000);
    }
});

print "starting second task" . PHP_EOL;
$b = Task::async(function () {
    for ($i = 0; $i < 5; $i++) {
        print "2 - " . $i . PHP_EOL;
        delay(1000);
    }
});

Task::await($a);
Task::await($b);
