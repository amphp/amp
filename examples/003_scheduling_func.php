<?php

require __DIR__ . '/../vendor/autoload.php';

Amp\run(function() {
    $ticker = function() {
        $now = time();
        $vowel = ($now % 2) ? 'i' : 'o';
        echo "t{$vowel}ck ", $now, "\n";
    };

    // Execute the specified callback ASAP in the next event loop iteration. There is no
    // need to clear an "immediately" watcher after execution. The Reactor will automatically
    // garbage collect resources associated with one-time events after they finish executing.
    Amp\immediately($ticker);

    // Execute every $msInterval milliseconds until the resulting $watcherId is canceled.
    // At some point in the future we need to cancel this watcher or our program will never end.
    $repeatingWatcherId = Amp\repeat($ticker, $msInterval = 1000);

    // Five seconds from now let's cancel the repeating ticker we just registered
    Amp\once(function() use ($repeatingWatcherId) {
        Amp\cancel($repeatingWatcherId);
        echo "Cancelled repeating ticker\n";
    }, $msDelay = 5000);

    // After about five seconds the program will exit on its own. Why? This happens because in
    // that time frame we will have cancelled the repeating watcher we registered using repeat()
    // and the two one-off events (immediately() + once()) are automatically garbage collected
    // by the Reactor after they execute.
});
