<?php

namespace Interop\Async\Loop;

/**
 * MUST be thrown if any operation (except disable() and cancel()) is attempted with an invalid watcher identifier.
 *
 * An invalid watcher identifier is any identifier that is not yet emitted by the driver or cancelled by the user.
 */
class InvalidWatcherException extends \Exception
{

}
