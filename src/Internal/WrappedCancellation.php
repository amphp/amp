<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

/**
 * Forwards cancellation to another cancellation, keeping this object's capabilities tied to that one.
 *
 * @psalm-purity-template read-props <= C(read-props|write-this-props|write-props|write-refs|io) <= read-props|write-this-props|write-props|write-refs|io
 * @implements Cancellation[C]
 *
 * @internal
 */
final class WrappedCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param Cancellation[C] $cancellation
     *
     * @psalm-capabilities read-props
     */
    public function __construct(
        private readonly Cancellation $cancellation
    ) {
    }

    /**
     * @psalm-capabilities read-props
     * @psalm-purity-from-template C
     */
    #[\Override]
    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    /**
     * @psalm-capabilities read-props
     * @psalm-purity-from-template C
     */
    #[\Override]
    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    /** @psalm-capabilities read-props */
    #[\Override]
    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-props|write-refs
     */
    #[\Override]
    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }
}
