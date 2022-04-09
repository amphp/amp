<?php

namespace Amp;

trait ForbidSerialization
{
    final public function __serialize(): never
    {
        throw new \Error(__CLASS__ . ' does not support serialization');
    }
}
