<?php

namespace Amp;

class UvIoWatcher extends Struct {
    public $id;
    public $uvStruct;
    public $callback;
    public $stream;
    public $pollFlag;
    public $mode;
    public $isEnabled;
}
