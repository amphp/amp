#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Loop;

if (stream_set_blocking(STDIN, false) !== true) {
    fwrite(STDERR, "Unable to set STDIN to non-blocking" . PHP_EOL);
    exit(1);
}

print "Write something and hit enter" . PHP_EOL;

Loop::onReadable(STDIN, function ($watcher, $stream) {
    $chunk = fread($stream, 8192);

    print "Read " . strlen($chunk) . " bytes" . PHP_EOL;
});

Loop::run();
