<?php

namespace Interop\Async\Loop;

/**
 * MUST be thrown if any operation (except cancel()) is attempted with an invalid watcher identifier.
 * [Invalid watcher identifier: any identifier not yet emitted by the driver or cancelled by the user]
 */
class InvalidWatcherException extends \LogicException
{

}
