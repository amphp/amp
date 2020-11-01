<?php

namespace Amp;

/**
 * Creates a successful promise using the given value (which can be any value except an object implementing
 * `Amp\Promise`).
 *
 * @template-covariant TValue
 * @template-implements Promise<TValue>
 */
final class Success implements Promise
{
    private mixed $value;

    /**
     * @param mixed $value Anything other than a Promise object.
     *
     * @psalm-param TValue $value
     *
     * @throws \Error If a promise is given as the value.
     */
    public function __construct(mixed $value = null)
    {
        if ($value instanceof Promise) {
            throw new \Error("Cannot use a promise as success value");
        }

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
            Loop::defer(static function () use ($e): void {
                throw $e;
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved): void
    {
        Loop::defer(fn() => $onResolved(null, $this->value));
    }
}
