<?php

namespace Amp;

/**
 * Allows consecutive transformation of an iterator.
 */
class IteratorTransform implements Iterator {
    private $applied = false;
    private $iterator;

    public function __construct(Iterator $iterator) {
        $this->iterator = $iterator;
    }

    /**
     * Applies a stream transformation.
     *
     * The given callback will be called with the iterator as first and an emit callable as second parameter. Generators
     * will automatically be run as coroutine. Return values are ignored.
     *
     * @param callable $callback
     *
     * @return IteratorTransform
     */
    public function apply(callable $callback): self {
        $this->applied = true;

        return new self(new Producer(function (callable $emit) use ($callback) {
            if (false) {
                // ensure it's a generator
                yield;
            }

            $return = $callback($this->iterator, $emit);

            if ($return instanceof \Generator) {
                yield from $return;
            }
        }));
    }

    /** @inheritdoc */
    public function advance(): Promise {
        if ($this->applied) {
            return new Success(false);
        }

        return $this->iterator->advance();
    }

    /** @inheritdoc */
    public function getCurrent() {
        if ($this->applied) {
            throw new \Error("IteratorTransform::apply() as already been called and the iterator been emptied.");
        }

        return $this->iterator->getCurrent();
    }
}
