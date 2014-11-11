<?php

namespace Amp;

class UvPoll extends Struct {
    public $flags;
    public $handle;
    public $callback;
    public $readers = [];
    public $writers = [];
    public $disable = [];
}
