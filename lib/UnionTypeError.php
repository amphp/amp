<?php

namespace Amp;

class UnionTypeError extends \TypeError {
    /**
     * @param string[] $expected Array of expected type names.
     * @param mixed $given Value given.
     */
    public function __construct(array $expected, $given) {
        parent::__construct(\sprintf(
            "Expected one of the following types: %s; %s given",
            \implode(", ", $expected),
            \is_object($given) ? \sprintf("instance of %s", \get_class($given)) : \gettype($given)
        ));
    }
}
