<?php

namespace Amp\Internal;

use Amp\Iterator;
use Amp\Promise;

/**
 * Wraps an Iterator instance that has public methods to emit, complete, and fail into an object that only allows
 * access to the public API methods.
 */
class PrivateIterator implements Iterator
{
    /** @var \Amp\Iterator */
    private $iterator;

    public function __construct(Iterator $iterator)
    {
        $this->iterator = $iterator;
    }

    public function advance(): Promise
    {
        return $this->iterator->advance();
    }

    public function getCurrent()
    {
        return $this->iterator->getCurrent();
    }
}
