<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;
use function Amp\await;

class DiscardTest extends AsyncTestCase
{
    public function testEmpty(): void
    {
        self::assertSame(0, await(Pipeline\discard(Pipeline\fromIterable([]))));
    }

    public function testCount(): void
    {
        self::assertSame(3, await(Pipeline\discard(Pipeline\fromIterable(['a', 1, false], 1))));
    }
}
