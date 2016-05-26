<?php

namespace Amp\Loop\Internal;

class Watcher {
    const DEFER    = 0b00000001;
    const TIMER    = 0b00000010;
    const DELAY    = 0b00000110;
    const REPEAT   = 0b00001010;
    const IO       = 0b00010000;
    const READABLE = 0b00110000;
    const WRITABLE = 0b01010000;
    const SIGNAL   = 0b10000000;

    /**
     * @var int
     */
    public $type;

    /**
     * @var string
     */
    public $id;

    /**
     * @var callable
     */
    public $callback;

    /**
     * @var mixed
     */
    public $data;

    /**
     * @var mixed
     */
    public $value;
}
