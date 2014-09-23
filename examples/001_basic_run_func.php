<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Running the reactor gives control of program control to the Amp event loop. Once started,
 * the reactor will only stop under one of the following two conditions:
 *
 *   (1) No scheduled events remain outstanding and no IO streams are registered for observation
 *   (2) The event reactor is explicitly stopped using Amp\stop() or calling
 *       Reactor::stop() on the running Reactor instance.
 *
 * The event reactor is our task scheduler. It controls program flow as long as it runs.
 *
 * This example uses Amp's global function API to interact with the event loop. As it really
 * never makes sense to have multiple event loop instances in a single-threaded application the
 * function API is useful for interacting with a statically restricted global loop.
 */

define('RUN_TIME', 10);
printf("Each line you type will be echoed back for the next %d seconds ...\n\n", RUN_TIME);

Amp\run(function() {
    // Set the STDIN stream to "non-blocking" mode
    stream_set_blocking(STDIN, false);

    // Echo back the line each time there is readable data on STDIN
    Amp\onReadable(STDIN, function() {
        if ($line = fgets(STDIN)) {
            echo "INPUT> ", $line, "\n";
        }
    });

    // Countdown RUN_TIME seconds then end the event loop
    $secondsRemaining = RUN_TIME;
    Amp\repeat(function() use (&$secondsRemaining) {
        if (--$secondsRemaining > 0) {
            echo "$secondsRemaining seconds to shutdown\n";
        } else {
            Amp\stop(); // <-- explicitly stop the loop
        }
    }, $msInterval = 1000);
});
