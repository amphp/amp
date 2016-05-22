<?php

namespace Amp\Awaitable\Exception;

class InvalidYieldException extends \DomainException {
    public function __construct(\Generator $generator, $yielded) {
        $prefix = \sprintf(
            "Unexpected yield (Awaitable expected); %s yielded at key %s",
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
