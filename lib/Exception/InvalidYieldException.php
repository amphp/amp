<?php

namespace Amp\Exception;

class InvalidYieldException extends \DomainException {
    /**
     * InvalidYieldException constructor.
     *
     * @param \Generator $generator
     * @param mixed $yielded
     * @param string $prefix
     */
    public function __construct(\Generator $generator, $yielded, $prefix) {
        $prefix = \sprintf(
            "%s; %s yielded at key %s",
            $prefix,
            \is_object($yielded) ? \get_class($yielded) : \gettype($yielded),
            $generator->key()
        );

        if (PHP_MAJOR_VERSION < 7 || !$generator->valid()) {
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
