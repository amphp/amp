<?php

namespace Amp;

trait ForbidCloning
{
    final protected function __clone()
    {
        throw new \Error(__CLASS__ . ' does not support cloning');
    }
}
