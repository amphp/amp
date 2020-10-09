<?php

namespace Amp\Loop;

/**
 * MUST be thrown if any operation (except disable() and cancel()) is attempted with an invalid watcher identifier.
 *
 * An invalid watcher identifier is any identifier that is not yet emitted by the driver or cancelled by the user.
 */
final class InvalidWatcherError extends \Error
{
    /** @var string */
    private string $watcherId;

    /**
     * @param string $watcherId The watcher identifier.
     * @param string $message The exception message.
     */
    public function __construct(string $watcherId, string $message)
    {
        $this->watcherId = $watcherId;
        parent::__construct($message);
    }

    /**
     * @return string The watcher identifier.
     */
    public function getWatcherId(): string
    {
        return $this->watcherId;
    }
}
