<?php

namespace Amp;

final class InvalidYieldError extends \Error {
    /**
     * @param \Generator $generator
     * @param string $prefix
     */
    public function __construct(\Generator $generator, string $prefix) {
        $yielded = $generator->current();
        $prefix .= \sprintf(
            "; %s yielded at key %s",
            \is_object($yielded) ? \get_class($yielded) : \gettype($yielded),
            \var_export($generator->key(), true)
        );

        if (!$generator->valid()) {
            parent::__construct($prefix);
            return;
        }

        $reflGen = new \ReflectionGenerator($generator);
        $exeGen = $reflGen->getExecutingGenerator();
        if ($isSubgenerator = ($exeGen !== $generator)) {
            $reflGen = new \ReflectionGenerator($exeGen);
        }

        parent::__construct(\sprintf(
            "%s on line %s in %s",
            $prefix,
            $reflGen->getExecutingLine(),
            $reflGen->getExecutingFile()
        ));
    }
}
