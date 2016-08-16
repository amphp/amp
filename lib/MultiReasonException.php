<?php

declare(strict_types=1);

namespace Amp;

class MultiReasonException extends \Exception {
    /**
     * @var \Throwable[]
     */
    private $reasons;

    /**
     * @param \Throwable[] $reasons Array of exceptions rejecting the awaitable.
     * @param string|null $message
     */
    public function __construct(array $reasons, string $message = null) {
        parent::__construct($message ?: "Too many awaitables were rejected");

        $this->reasons = $reasons;
    }

    /**
     * @return \Throwable[]
     */
    public function getReasons(): array {
        return $this->reasons;
    }
}
