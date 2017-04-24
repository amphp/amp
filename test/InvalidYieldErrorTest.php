<?php

namespace Amp\Test;

use Amp\InvalidYieldError;

class InvalidYieldErrorTest extends \PHPUnit\Framework\TestCase {
    public function testWithInvalidGenerator() {
        /** @var \Generator $gen */
        $gen = (function () {
            if (false) {
                yield;
            }
        })();

        $gen->current();

        $error = new InvalidYieldError($gen, "prefix message");
        $this->assertSame("prefix message; NULL yielded at key NULL", $error->getMessage());
    }

    public function testSubgenerator() {
        $subgen = (function () {
            yield "foo" => 42;
        })();

        /** @var \Generator $gen */
        $gen = (function () use ($subgen) {
            yield from $subgen;
        })();

        $error = new InvalidYieldError($gen, "prefix");
        $this->assertSame("prefix; integer yielded at key 'foo' on line " . (__LINE__ - 8) . " in " . __FILE__, $error->getMessage());
    }
}
