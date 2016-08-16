<?php

declare(strict_types=1);

namespace Amp;

class InvalidYieldError extends \Error {
    /**
     * @param \Generator $generator
     * @param string $prefix
     */
    public function __construct(\Generator $generator, string $prefix) {
        $yielded = $generator->current();
        $prefix .= \sprintf(
            "; %s yielded at key %s",
            \is_object($yielded) ? \get_class($yielded) : \gettype($yielded),
            $generator->key()
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
