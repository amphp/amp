<?php

/**
 * While the event reactor can be told to control program flow indefinitely using Reactor::run(),
 * you can also utilize Reactor::tick() to execute event loop tasks incrementally. This approach
 * allows integration of Alert-based tools with other event systems and avoids the need to explicitly
 * stop the reactor via Reactor::stop() to end program execution.
 */

require dirname(__DIR__) . '/autoload.php';

stream_set_blocking(STDIN, FALSE);

$reactor = (new Alert\ReactorFactory)->select();

$reactorHasControl = TRUE;

$reactor->onReadable(STDIN, function($stdin) use (&$reactorHasControl) {
    while ($line = fgets($stdin)) {
        $line = trim($line);
        if (!strcasecmp($line, 'quit') || !strcasecmp($line, 'q')) {
            $reactorHasControl = FALSE;
        } else {
            echo "Input: {$line}\n";
        }
    }
});

echo "\nAny input lines will now be echoed back to you.\nEnter 'q' or 'quit' to exit.\n\n";

while ($reactorHasControl) {
    $reactor->tick();
}
