<?php

namespace Amp;

use Interop\Async\Awaitable;

class InvalidYieldException extends \DomainException {
    /**
     * @param \Generator $generator
     * @param string|null $prefix
     */
    public function __construct(\Generator $generator, $prefix = null) {
        if ($prefix === null) {
            $prefix = \sprintf("Unexpected yield (%s or %s::result() expected)", Awaitable::class, Coroutine::class);
        }

        $yielded = $generator->current();
        $prefix .= \sprintf(
            "; %s yielded at key %s",
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
