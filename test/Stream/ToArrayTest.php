<?php

namespace Amp\Test\Stream;

use Amp\Stream;
use Amp\Test\BaseTest;
use function Amp\Promise\wait;

class ToArrayTest extends BaseTest
{
    public function testNonEmpty()
    {
        $iterator = Stream\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], wait(Stream\toArray($iterator)));
    }

    public function testEmpty()
    {
        $iterator = Stream\fromIterable([], 5);
        $this->assertSame([], wait(Stream\toArray($iterator)));
    }
}
