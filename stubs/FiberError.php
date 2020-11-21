<?php

/**
 * Exception thrown due to invalid fiber actions, such as suspending from outside a fiber.
 */
final class FiberError extends Error
{
    /**
     * Constructor throws to prevent user code from throwing FiberError.
     */
    public function __construct()
    {
        throw new \Error('The "FiberError" class is reserved for internal use and cannot be manually instantiated');
    }
}
