<?php

declare(strict_types=1);

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Awaitable implementation that should not be returned from a public API, but used only internally.
 */
final class Future implements Awaitable {
    use Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}
