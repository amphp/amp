<?php

namespace Amp\Loop\Internal;

class Watcher
{
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
     * @param callable $callback
     * @param mixed $data
     */
    public function __construct(callable $callback, $data = null)
    {
        $this->id = \spl_object_hash($this);
        $this->callback = $callback;
        $this->data = $data;
    }
}
