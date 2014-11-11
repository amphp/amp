<?php

namespace Amp;

class UvIoWatcher extends Struct {
    public $id;
    public $mode;
    public $poll;
    public $stream;
    public $callback;
    public $isEnabled;
}
