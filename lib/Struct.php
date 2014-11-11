<?php

namespace Amp;

class Struct {
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
