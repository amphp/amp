<?php

namespace Amp\Loop\Internal;

class Watcher {
    const DEFER    = 0b00000001;
    const TIMER    = 0b00000110;
    const DELAY    = 0b00000010;
    const REPEAT   = 0b00000100;
    const IO       = 0b00011000;
    const READABLE = 0b00001000;
    const WRITABLE = 0b00010000;
    const SIGNAL   = 0b00100000;

    /**
     * @var int
     */
    public $type;

    /**
     * @var bool
     */
    public $enabled = true;

    /**
     * @var bool
     */
    public $referenced = true;

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
