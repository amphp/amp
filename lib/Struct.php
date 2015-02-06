<?php

namespace Amp;

/**
 * A "safe" Struct class for public property aggregators
 *
 * This class is intended to make using public properties a little safer by throwing when
 * nonexistent property names are read or written. All property aggregation classes in the
 * Amp library descend from Struct somewhere in their inheritance hierarchies.
 */
abstract class Struct {
    final public function __get($property) {
        throw new \DomainException(
            $this->generatePropertyError($property)
        );
    }

    final public function __set($property, $value) {
        throw new \DomainException(
            $this->generatePropertyError($property)
        );
    }

    private function generatePropertyError($property) {
        return sprintf("Struct property %s::%s does not exist", get_class($this), $property);
    }
}
