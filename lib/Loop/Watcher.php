<?php

namespace Amp\Loop;

use Amp\Struct;

/**
 * @template TValue as (int|resource|null)
 *
 * @psalm-suppress MissingConstructor
 */
class Watcher
{
    use Struct;

    const IO = 0b00000011;
    const READABLE = 0b00000001;
    const WRITABLE = 0b00000010;
    const DEFER = 0b00000100;
    const TIMER = 0b00011000;
    const DELAY = 0b00001000;
    const REPEAT = 0b00010000;
    const SIGNAL = 0b00100000;

    public int $type;

    public bool $enabled = true;

    public bool $referenced = true;

    public string $id;

    /** @var callable */
    public $callback;

    /**
     * Data provided to the watcher callback.
     *
     * @var mixed
     */
    public $data;

    /**
     * Watcher-dependent value storage. Stream for IO watchers, signal number for signal watchers, interval for timers.
     *
     * @var resource|int|null
     * @psalm-var TValue
     */
    public $value;

    /** @var int|null */
    public ?int $expiration = null;
}
