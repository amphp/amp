<?php

namespace Amp\Test\Stream;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Stream;
use function Amp\Stream\discard;

class DiscardTest extends AsyncTestCase
{
    public function testEmpty(): \Generator
    {
        $this->assertSame(0, yield discard(Stream\fromIterable([])));
    }

    public function testCount(): \Generator
    {
        $this->assertSame(3, yield discard(Stream\fromIterable(['a', 1, false], 1)));
    }
}
