<?php

namespace Amp\Awaitable\Exception;

class MultiReasonException extends \Exception {
    /**
     * @var \Throwable[]|\Exception[]
     */
    private $reasons;

    /**
     * @param \Exception[] $reasons Array of exceptions rejecting the awaitable.
     * @param string|null $message
     */
    public function __construct(array $reasons, $message = null)
    {
        parent::__construct($message ?: 'Too many awaitables were rejected');

        $this->reasons = $reasons;
    }

    /**
     * @return \Throwable[]|\Exception[]
     */
    public function getReasons()
    {
        return $this->reasons;
    }
}
