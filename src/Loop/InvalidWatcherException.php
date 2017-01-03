<?php

namespace Interop\Async\Loop;

/**
 * MUST be thrown if any operation (except disable() and cancel()) is attempted with an invalid watcher identifier.
 *
 * An invalid watcher identifier is any identifier that is not yet emitted by the driver or cancelled by the user.
 */
class InvalidWatcherException extends \Exception
{
    /** @var string */
    private $watcherId;

    /**
     * @param string $watcherId The watcher identifier.
     * @param string|null $message The exception message.
     */
    public function __construct($watcherId, $message = null)
    {
        $this->watcherId = $watcherId;

        if ($message === null) {
            $message = "An invalid watcher identifier has been used: '{$watcherId}'";
        }

        parent::__construct($message);
    }

    /**
     * @return string The watcher identifier.
     */
    public function getWatcherId()
    {
        return $this->watcherId;
    }
}
