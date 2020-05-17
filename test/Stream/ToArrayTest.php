<?php

namespace Amp\Test\Stream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Stream;

class ToArrayTest extends AsyncTestCase
{
    public function testNonEmpty()
    {
        $stream = Stream\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], yield Stream\toArray($stream));
    }

    public function testEmpty()
    {
        $stream = Stream\fromIterable([], 5);
        $this->assertSame([], yield Stream\toArray($stream));
    }
}
