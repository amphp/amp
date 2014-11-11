<?php

namespace Amp;

class LibeventWatcher extends Struct {
    public $id;
    public $eventResource;
    public $stream;
    public $signo;
    public $callback;
    public $wrapper;
    public $msDelay = -1;
    public $isEnabled = TRUE;
}
