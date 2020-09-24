<?php

/**
 * Exception thrown due to invalid fiber actions, such as suspending from outside a fiber.
 */
final class FiberError extends Error
{
    /**
     * Private constructor to prevent user code from throwing FiberError.
     */
    public function __construct(string $message)
    {
        throw new \Error("FiberError cannot be constructed manually");
    }
}
