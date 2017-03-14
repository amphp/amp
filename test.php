<?php

require "vendor/autoload.php";

use Amp\Loop;

Loop::set(new Loop\NativeDriver);

var_dump(stream_get_meta_data(STDIN));

Loop::run(function () {
    Loop::onReadable(STDIN, function ($watcherId) {
        var_dump("READ", fread(STDIN, 8192));
        var_dump("EOF", feof(STDIN));

        if (feof(STDIN)) {
            Loop::cancel($watcherId);
        }
    });
});
