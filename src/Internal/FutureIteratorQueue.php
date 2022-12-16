<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Future;
use Revolt\EventLoop\Suspension;

/**
 * @template Tk
 * @template Tv
 *
 * @internal
 */
final class FutureIteratorQueue
{
    /**
     * @var list<array{Tk, Future<Tv>}>
     */
    public array $items = [];

    /**
     * @var array<string, FutureState<Tv>>
     */
    public array $pending = [];

    public ?Suspension $suspension = null;
}
