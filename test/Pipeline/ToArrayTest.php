<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class ToArrayTest extends AsyncTestCase
{
    public function testNonEmpty(): void
    {
        $pipeline = Pipeline\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], Pipeline\toArray($pipeline));
    }

    public function testEmpty(): void
    {
        $pipeline = Pipeline\fromIterable([], 5);
        $this->assertSame([], Pipeline\toArray($pipeline));
    }
}
