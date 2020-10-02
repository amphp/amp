<?php

namespace Amp\Test\Iterator;

use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;

class ToArrayTest extends AsyncTestCase
{
    public function testNonEmpty(): \Generator
    {
        $iterator = Iterator\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], yield Iterator\toArray($iterator));
    }

    public function testEmpty(): \Generator
    {
        $iterator = Iterator\fromIterable([], 5);
        $this->assertSame([], yield Iterator\toArray($iterator));
    }
}
