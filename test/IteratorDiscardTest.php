<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\Iterator\discard;

class IteratorDiscardTest extends AsyncTestCase
{
    public function testEmpty(): \Generator
    {
        $this->assertSame(0, yield discard(Iterator\fromIterable([])));
    }

    public function testCount(): \Generator
    {
        $this->assertSame(3, yield discard(Iterator\fromIterable(['a', 1, false], 1)));
    }
}
