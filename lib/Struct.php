<?php

namespace Amp;

/**
 * A "safe" struct trait for public property aggregators
 *
 * This trait is intended to make using public properties a little safer by throwing when
 * nonexistent property names are read or written.
 */
trait Struct {
    /**
     * The minimum percentage [0-100] at which to recommend a similar property
     * name when generating error messages.
     */
    private $__propertySuggestThreshold = 70;

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
        $suggestion = $this->suggestPropertyName($property);
        $suggestStr = ($suggestion == "") ? "" : " ... did you mean \"{$suggestion}?\"";

        return sprintf(
            "%s property \"%s\" does not exist%s",
            get_class($this),
            $property,
            $suggestStr
        );
    }

    private function suggestPropertyName($badProperty) {
        $badProperty = strtolower($badProperty);
        $bestMatch = "";
        $bestMatchPercentage = 0.00;
        $byRefPercentage = 0.00;
        foreach ($this as $property => $value) {
            // Never suggest properties that begin with an underscore
            if ($property[0] === "_") {
                continue;
            }
            \similar_text($badProperty, strtolower($property), $byRefPercentage);
            if ($byRefPercentage > $bestMatchPercentage) {
                $bestMatchPercentage = $byRefPercentage;
                $bestMatch = $property;
            }
        }

        return ($bestMatchPercentage >= $this->__propertySuggestThreshold) ? $bestMatch : "";
    }
}
