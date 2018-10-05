<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\PHPUnit\TestCase;
use function Amp\Promise\wait;

class CollectTest extends TestCase
{
    public function testCollect()
    {
        $iterator = Iterator\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], wait(Iterator\collect($iterator)));
    }

    public function testEmpty()
    {
        $iterator = Iterator\fromIterable([], 5);
        $this->assertSame([], wait(Iterator\collect($iterator)));
    }
}
