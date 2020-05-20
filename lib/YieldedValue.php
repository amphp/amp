<?php

namespace Amp;

/**
 * @template-covariant TValue
 */
final class YieldedValue
{
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     *
     * @psalm-param TValue $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Catches any destructor exception thrown and rethrows it to the event loop.
     */
    public function __destruct()
    {
        try {
            $this->value = null;
        } catch (\Throwable $e) {
            Loop::defer(static function () use ($e) {
                throw $e;
            });
        }
    }

    /**
     * @return mixed
     *
     * @psalm-return TValue
     */
    public function unwrap()
    {
        return $this->value;
    }
}
