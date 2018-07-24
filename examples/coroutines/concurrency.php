<?php

require __DIR__ . '/../../vendor/autoload.php';

use Concurrent\Task;
use function Amp\delay;

// Shows how two for loops are executed concurrently.

// Note that the first two items are printed _before_ the Loop::run()
// as they're executed immediately and do not register any timers or defers.

function printWithTime(string $message): void
{
    static $start = null;
    $start = $start ?? \microtime(true);

    \printf("%' 4d ms ", \round((\microtime(true) - $start) * 1000));

    print $message . PHP_EOL;
}

printWithTime("-- begin task A --");
Task::async(function () {
    for ($i = 0; $i < 8; $i++) {
        printWithTime(" A :: " . $i);
        delay(1000);
    }

    printWithTime("-- end of task A --");
});

printWithTime("-- begin task B --");
for ($i = 0; $i < 3; $i++) {
    printWithTime("           B :: " . $i);
    delay(2000);
}

printWithTime("-- end of task B --");
