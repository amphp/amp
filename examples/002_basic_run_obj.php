<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Reactor;

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
 * This example uses Amp's instance method API to interact with the event loop. Although the
 * event reactor instance is a true application global it is often useful for testing and API
 * transparency to to pass around the Reactor instance explicitly (as opposed to using the global
 * function API).
 *
 * IMPORTANT: Bugs arising from instantiating multiple Reactor instances in a single-threaded
 * application can be extremely difficult to troubleshoot. Be very careful to pass around only
 * a single shared event Reactor instance when using Amp's object API.
 */

define('RUN_TIME', 10);
printf("Each line you type will be echoed back for the next %d seconds ...\n\n", RUN_TIME);

Amp\run(function(Reactor $reactor) {
    // Set the STDIN stream to "non-blocking" mode
    stream_set_blocking(STDIN, false);

    // Echo back the line each time there is readable data on STDIN
    $reactor->onReadable(STDIN, function() {
        if ($line = fgets(STDIN)) {
            echo "INPUT> ", $line, "\n";
        }
    });

    // Countdown RUN_TIME seconds then end the event loop
    $secondsRemaining = RUN_TIME;
    $reactor->repeat(function() use (&$secondsRemaining, $reactor) {
        if (--$secondsRemaining > 0) {
            echo "$secondsRemaining seconds to shutdown\n";
        } else {
            $reactor->stop();
        }
    }, $msInterval = 1000);
});
