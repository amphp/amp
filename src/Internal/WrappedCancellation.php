<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

/**
 * @internal
 */
final class WrappedCancellation implements Cancellation
{
    use ForbidCloning;
    use ForbidSerialization;

    public function __construct(
        private readonly Cancellation $cancellation
    ) {
    }

    public function subscribe(\Closure $callback): string
    {
        return $this->cancellation->subscribe($callback);
    }

    public function unsubscribe(string $id): void
    {
        $this->cancellation->unsubscribe($id);
    }

    public function isRequested(): bool
    {
        return $this->cancellation->isRequested();
    }

    public function throwIfRequested(): void
    {
        $this->cancellation->throwIfRequested();
    }
}
