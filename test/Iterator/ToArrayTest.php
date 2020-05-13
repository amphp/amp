<?php

namespace Amp\Test\Iterator;

use Amp\Iterator;
use Amp\Test\BaseTest;
use function Amp\Promise\wait;

class ToArrayTest extends BaseTest
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
