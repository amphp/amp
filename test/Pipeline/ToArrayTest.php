<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Pipeline;

class ToArrayTest extends AsyncTestCase
{
    public function testNonEmpty()
    {
        $pipeline = Pipeline\fromIterable(["abc", "foo", "bar"], 5);
        $this->assertSame(["abc", "foo", "bar"], yield Pipeline\toArray($pipeline));
    }

    public function testEmpty()
    {
        $pipeline = Pipeline\fromIterable([], 5);
        $this->assertSame([], yield Pipeline\toArray($pipeline));
    }
}
