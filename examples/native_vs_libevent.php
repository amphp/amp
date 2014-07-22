<?php

/**
 * examples/native_vs_libevent.php
 */

require __DIR__ . '/../vendor/autoload.php';

define('RUN_SECONDS', 3);

// Native event reactor test ---------------------------------------------------------------------->

$reactor = new Alert\NativeReactor;
$nativeCount = 0;
$nativeCounter = function() use (&$nativeCount) { $nativeCount++; };
$reactor->repeat($nativeCounter, $interval = 0);
$reactor->once([$reactor, 'stop'], $delay = RUN_SECONDS*1000);

echo "Running NativeReactor test ... ";
$reactor->run();
$nativeCount = round($nativeCount/RUN_SECONDS);
$nativeCount = str_pad(number_format($nativeCount), 10, ' ', STR_PAD_LEFT) . ' per second';
echo "DONE.\n";

// Libevent reactor test -------------------------------------------------------------------------->

if (extension_loaded('libevent')) {
    $reactor = new Alert\LibeventReactor;
    $libeventCount = 0;
    $libeventCounter = function() use (&$libeventCount) { $libeventCount++; };
    $reactor->repeat($libeventCounter, $interval = 0);
    $reactor->once([$reactor, 'stop'], $delay = RUN_SECONDS*1000);

    echo "Running LibeventReactor test ... ";
    $reactor->run();
    $libeventCount = round($libeventCount/RUN_SECONDS);
    $libeventCount = str_pad(number_format($libeventCount), 10, ' ', STR_PAD_LEFT) . ' per second';
    echo "DONE.\n";
} else {
    $libeventCount = 'N/A (ext/libevent not available)';
}

// Output results --------------------------------------------------------------------------------->

$results = <<<EOT

--------------------------------------------------
Counter Callback Invocation Test (%s second test)
--------------------------------------------------

NativeReactor:   %s
LibeventReactor: %s


EOT;

echo sprintf($results, (RUN_SECONDS / 1000), $nativeCount, $libeventCount);
