<?php

/**
 * examples/native_vs_libevent.php
 * 
 * The libevent reactor will always be much faster than the native reactor. This example
 * demonstrates the speed difference between the native event reactor and the libevent reactor. To
 * gauge the performance difference we use a simple closure to track the number of invocations
 * during a given time period. The results are output after the test completes.
 */

require dirname(__DIR__) . '/autoload.php';

define('RUN_TIME', 3);

// Native event reactor test ---------------------------------------------------------------------->

$reactor = new Alert\NativeReactor;
$nativeCount = 0;
$nativeCounter = function() use (&$nativeCount) { $nativeCount++; };
$reactor->repeat($nativeCounter, $interval = 0);
$reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = RUN_TIME);

echo "Running NativeReactor test ... ";
$reactor->run();
$nativeCount = round($nativeCount/RUN_TIME);
$nativeCount = str_pad(number_format($nativeCount), 10, ' ', STR_PAD_LEFT) . ' per second';
echo "DONE.\n";

// Libevent reactor test -------------------------------------------------------------------------->

if (extension_loaded('libevent')) {
    $reactor = new Alert\LibeventReactor;
    $libeventCount = 0;
    $libeventCounter = function() use (&$libeventCount) { $libeventCount++; };
    $reactor->repeat($libeventCounter, $interval = 0);
    $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = RUN_TIME);
    
    echo "Running LibeventReactor test ... ";
    $reactor->run();
    $libeventCount = round($libeventCount/RUN_TIME);
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

echo sprintf($results, RUN_TIME, $nativeCount, $libeventCount);
