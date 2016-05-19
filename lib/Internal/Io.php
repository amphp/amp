<?php

namespace Amp\Loop\Internal;

class Io extends Watcher
{
    /**
     * @var resource
     */
    public $stream;

    /**
     * @var int
     */
    public $key;

    /**
     * @param resource $stream
     * @param callable $callback
     * @param mixed $data
     */
    public function __construct($stream, callable $callback, $data = null)
    {
        parent::__construct($callback, $data);

        $this->stream = $stream;
        $this->key = (int) $stream;
    }
}