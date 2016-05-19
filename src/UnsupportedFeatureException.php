<?php

use Interop\Async;

/**
 * Must be thrown if an optional feature is not supported by the current driver
 * or system.
 */
class UnsupportedFeatureException extends \RuntimeException
{

}
