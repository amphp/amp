<?php

/**
 * Exception thrown when destroying a fiber. This exception cannot be caught by user code.
 */
final class FiberExit extends Exception
{
    /**
     * Constructor throws to prevent user code from throwing FiberExit.
     */
    public function __construct()
    {
        throw new \Error('The "FiberExit" class is reserved for internal use and cannot be manually instantiated');
    }
}
