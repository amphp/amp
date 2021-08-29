<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class DiscardTest extends AsyncTestCase
{
    public function testEmpty(): void
    {
        self::assertSame(0, Pipeline\discard(Pipeline\fromIterable([]))->join());
    }

    public function testCount(): void
    {
        self::assertSame(3, Pipeline\discard(Pipeline\fromIterable(['a', 1, false], 0.001))->join());
    }
}
