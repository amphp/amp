<?php

use Interop\Async\EventLoop;

/**
 * Must be thrown if an optional feature is not supported by the current driver
 * or system.
 */
class UnsupportedFeatureException extends \RuntimeException { }
