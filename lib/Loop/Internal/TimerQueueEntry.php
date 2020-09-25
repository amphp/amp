<?php

namespace Amp\Loop\Internal;

use Amp\Loop\Watcher;
use Amp\Struct;

/**
 * @internal
 */
final class TimerQueueEntry
{
    use Struct;

    public Watcher $watcher;

    public int $expiration;

    /**
     * @param Watcher $watcher
     * @param int     $expiration
     */
    public function __construct(Watcher $watcher, int $expiration)
    {
        $this->watcher = $watcher;
        $this->expiration = $expiration;
    }
}
