<?php declare(strict_types=1);

namespace Amp\Internal;

use Amp\Future;
use Revolt\EventLoop\Suspension;

/**
 * @template Tk of array-key
 * @template Tv
 *
 * @internal
 */
final class FutureIteratorQueue
{
    /** @var array<array{Tk, Future<Tv>}> */
    public array $items = [];

    /** @var array<string, FutureState<Tv>> */
    public array $pending = [];

    /** @var Suspension<array{Tk, Future<Tv>}|null>|null */
    public ?Suspension $suspension = null;
}
