<?php

namespace Alert;

class LibeventWatcher {
    public $id;
    public $eventResource;
    public $stream;
    public $callback;
    public $wrapper;
    public $interval = -1;
    public $isEnabled = TRUE;
}
