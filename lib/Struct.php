<?php

namespace Amp;

/**
 * A "safe" struct trait for public property aggregators
 *
 * This trait is intended to make using public properties a little safer by throwing when
 * nonexistent property names are read or written.
 */
trait Struct {
    final public function __get($property) {
        throw new \DomainException(
            $this->generateStructPropertyError($property)
        );
    }

    final public function __set($property, $value) {
        throw new \DomainException(
            $this->generateStructPropertyError($property)
        );
    }

    private function generateStructPropertyError($property) {
        return sprintf("Struct property %s::%s does not exist", get_class($this), $property);
    }
}
