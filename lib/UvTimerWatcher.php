<?php

namespace Amp;

class UvTimerWatcher extends Struct {
    public $id;
    public $uvStruct;
    public $callback;
    public $msDelay;
    public $msInterval;
    public $mode;
    public $isEnabled;
}
