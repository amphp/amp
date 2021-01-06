<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use Amp\Loop;
use function Amp\async;
use function Amp\await;

// Note that the closure declares int as a return type, not Promise or Generator, but executes like a coroutine.
$callback = function (int $id): int {
    return await(new Delayed(1000, $id)); // Await promise resolution.
};

$timer = Loop::repeat(100, function () {
    echo ".", PHP_EOL; // This repeat timer is to show the loop is not being blocked.
});
Loop::unreference($timer); // Unreference timer so the loop exits automatically when all tasks complete.

// Invoking $callback returns an int, but is executed asynchronously.
$result = $callback(1); // Call a subroutine within this green thread, taking 1 second to return.
\var_dump($result);

// Simultaneously runs two new green threads, await their resolution in this green thread.
$result = await([  // Executed simultaneously, only 1 second will elapse during this await.
    async($callback, 2),
    async($callback, 3),
]);
\var_dump($result); // Executed after 2 seconds.

$result = $callback(4); // Call takes 1 second to return.
\var_dump($result);

// array_map() takes 2 seconds to execute as the calls are not concurrent, but this shows that fibers are
// supported by internal callbacks.
$result = \array_map($callback, [5, 6]);
\var_dump($result);
