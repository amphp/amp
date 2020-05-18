<?php

namespace Amp;

/**
 * @template-covariant TValue
 */
final class YieldedValue
{
    /** @var TValue */
    private $value;

    /**
     * @param TValue $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return TValue
     */
    public function unwrap()
    {
        return $this->value;
    }
}
