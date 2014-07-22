<?php

/**
 * examples/scheduling.php
 */

require __DIR__ . '/../vendor/autoload.php';

$reactor = (new Alert\ReactorFactory)->select();

$ticker = function() { echo "tick ", time(), PHP_EOL; };

// Execute in the next event loop iteration
$reactor->immediately($ticker);

// Execute every $msInterval seconds until cancelled
$reactor->repeat($ticker, $msInterval = 1000);

// Execute once after $msDelay milliseconds
$reactor->once(function() use ($reactor) { $reactor->stop(); }, $msDelay = 3000);

// Release the hounds!
$reactor->run();
