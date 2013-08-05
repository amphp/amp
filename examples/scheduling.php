<?php

/**
 * examples/scheduling.php
 */

require dirname(__DIR__) . '/autoload.php';

$reactor = (new Alert\ReactorFactory)->select();

$ticker = function() { echo "tick ", time(), PHP_EOL; };

// Execute in the next event loop iteration
$reactor->immediately($ticker);

// Execute every $interval seconds until cancelled
$reactor->repeat($ticker, $interval = 1);

// Execute once after $delay seconds
$reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 3);

// Release the hounds!
$reactor->run();
