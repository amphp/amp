<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a successful promise using the given value (which can be any value except an object implementing
 * `Amp\Promise` or `React\Promise\PromiseInterface`).
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
        if ($value instanceof Promise || $value instanceof ReactPromise) {
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
            Loop::defer(static function () use ($e) {
                throw $e;
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved): void
    {
        Loop::defer(function () use ($onResolved): void {
            $result = $onResolved(null, $this->value);

            if ($result === null) {
                return;
            }

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise || $result instanceof ReactPromise) {
                Promise\rethrow($result);
            }
        });
    }
}
