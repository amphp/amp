<?php

namespace Amp;

final class CompositeLengthException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
