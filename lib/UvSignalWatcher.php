<?php

namespace Amp;

class UvSignalWatcher extends Struct {
    public $id;
    public $mode;
    public $signo;
    public $uvStruct;
    public $callback;
    public $isEnabled;
}
