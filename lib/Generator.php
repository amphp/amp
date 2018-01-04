<?php

namespace Amp;

/**
 * Generator is a container for a Flow that can yield values using the yield() method and completed using the
 * complete() and fail() methods of this object. The contained Flow may be accessed using the iterate()
 * method. This object should not be part of a public API, but used internally to create and yield values to a Flow.
 */
final class Generator
{
    /** @var object Has public yield, complete, and fail methods. */
    private $generator;

    public function __construct()
    {
        $this->generator = new class {
            use Internal\Generator {
                yield as public;
                complete as public;
                fail as public;
            }
        };
    }

    /**
     * @return \Amp\Flow
     */
    public function iterate(): Flow
    {
        return $this->generator->flow();
    }

    /**
     * Yields a value to the flow.
     *
     * @param mixed $value
     * @param mixed $key Using null auto-generates an incremental integer key.
     *
     * @return \Amp\Promise
     */
    public function yield($value, $key = null): Promise
    {
        return $this->generator->yield($value, $key);
    }

    /**
     * Completes the flow.
     */
    public function complete()
    {
        $this->generator->complete();
    }

    /**
     * Fails the flow with the given reason.
     *
     * @param \Throwable $reason
     */
    public function fail(\Throwable $reason)
    {
        $this->generator->fail($reason);
    }
}
