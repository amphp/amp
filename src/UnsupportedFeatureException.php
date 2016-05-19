<?php

use Interop\Async;

/**
 * Must be thrown if a feature is not supported by the system.
 *
 * This might happen if PCNTL is missing and the loop driver doesn't support another way to dispatch signals.
 */
class UnsupportedFeatureException extends \RuntimeException
{

}
