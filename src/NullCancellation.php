<?php declare(strict_types=1);

namespace Amp;

/**
 * A NullCancellation can be used to avoid conditionals to check whether a cancellation has been provided.
 *
 * Instead of writing
 *
 * ```php
 * if ($cancellation) {
 *     $cancellation->throwIfRequested();
 * }
 * ```
 *
 * potentially multiple times, it allows writing
 *
 * ```php
 * $cancellation = $cancellation ?? new NullCancellation;
 *
 * // ...
 *
 * $cancellation->throwIfRequested();
 * ```
 *
 * instead.
 *
 * @implements Cancellation[read-props]
 *
 * @api
 */
final class NullCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @psalm-pure */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        return "null-cancellation";
    }

    /** @psalm-capabilities read-props */
    #[\Override]
    public function unsubscribe(string $id): void
    {
        // nothing to do
    }

    /** @psalm-pure */
    #[\Override]
    public function isRequested(): bool
    {
        return false;
    }

    /** @psalm-pure */
    #[\Override]
    public function throwIfRequested(): void
    {
        // nothing to do
    }
}
