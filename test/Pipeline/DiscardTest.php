<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class DiscardTest extends AsyncTestCase
{
    public function testEmpty(): \Generator
    {
        $this->assertSame(0, yield Pipeline\discard(Pipeline\fromIterable([])));
    }

    public function testCount(): \Generator
    {
        $this->assertSame(3, yield Pipeline\discard(Pipeline\fromIterable(['a', 1, false], 1)));
    }
}
