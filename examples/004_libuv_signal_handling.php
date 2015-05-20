<?php

/**
 * Process signals are "watchable" events just like timers and stream IO
 * availability. SignalReactor::onSignal() returns a unique watcher ID that
 * may be disabled/enabled/canceled like any other watcher.
 *
 * The available signal number constants vary by operating system, but you
 * can see the possible signals in your PHP install with the following
 * snippet:
 *
 *     <?php
 *     // Any constant beginning with SIG* is an available signal
 *     print_r((new ReflectionClass('UV'))->getConstants());
 */
require __DIR__ . '/../vendor/autoload.php';

(new Amp\UvReactor)->run(function(Amp\Reactor $reactor) {
    // Let's tick off output once per second so we can see activity.
    $reactor->repeat(function() {
            echo "tick: ", date('c'), "\n";
    }, $msInterval = 1000);

    // What to do when a SIGINT signal is received
    $reactor->onSignal(UV::SIGINT, function() {
        echo "Caught SIGINT! exiting ...\n";
        exit;
    });
});
