<?php

namespace Alert;

class LibeventWatcher {
    public $id;
    public $type;
    public $eventResource;
    public $stream;
    public $streamFlags;
    public $callback;
    public $wrapper;
    public $interval = -1;
    public $isEnabled = TRUE;
    public $isRepeating = FALSE;
}
