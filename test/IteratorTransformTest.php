<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\IteratorTransform;
use Amp\Loop;
use PHPUnit\Framework\TestCase;
use function Amp\Iterator\fromIterable;

class IteratorTransformTest extends TestCase {
    public function testCanFilter() {
        // Test that it applies backpressure
        $this->expectOutputString("0010101");

        $iterator = fromIterable(\range(0, 3));
        $count = 0;

        $iteratorTransform = new IteratorTransform($iterator);
        $iteratorTransform->apply(function (Iterator $iterator, $emit) {
            while (yield $iterator->advance()) {
                print "0";

                if ($iterator->getCurrent()) {
                    yield $emit($iterator->getCurrent());
                }
            }
        })->apply(function (Iterator $iterator) use (&$count) {
            while (yield $iterator->advance()) {
                print "1";

                $count++;
            }
        });

        $this->assertSame(3, $count);
    }

    public function testIsEmptyAfterApply() {
        Loop::run(function () {
            $iterator = fromIterable(\range(0, 3), 100);

            $iteratorTransform = new IteratorTransform($iterator);
            $iteratorTransform->apply(function (Iterator $iterator, $emit) {
                while (yield $iterator->advance());
            });

            $this->assertFalse(yield $iteratorTransform->advance());

            $this->expectException(\Error::class);
            $iteratorTransform->getCurrent();
        });
    }
}
