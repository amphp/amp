<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\Promise\wait;

class IteratorToArrayTest extends TestCase
{
    public function testNonEmpty()
    {
        $iterator = Iterator\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], wait(Iterator\toArray($iterator)));
    }

    public function testEmpty()
    {
        $iterator = Iterator\fromIterable([], 5);
        $this->assertSame([], wait(Iterator\toArray($iterator)));
    }
}
