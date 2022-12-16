<?php declare(strict_types=1);

namespace Amp;

final class CompositeLengthException extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
