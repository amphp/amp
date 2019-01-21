<?php

namespace Amp\Loop\Internal;

use Amp\Loop\Watcher;
use Amp\Struct;

class TimerQueueEntry
{
    use Struct;

    /** @var Watcher */
    public $watcher;

    /** @var int */
    public $expiration;
}
