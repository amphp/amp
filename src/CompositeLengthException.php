<?php declare(strict_types=1);

namespace Amp;

/**
 * Thrown when a future combinator is given fewer futures than it requires.
 *
 * @api
 */
final class CompositeLengthException extends \Exception
{
    /** @psalm-capabilities read-props */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
