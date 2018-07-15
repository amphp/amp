<?php

namespace Amp\Test;

use Amp\PHPUnit\TestCase;
use function Amp\delay;

class TaskTest extends TestCase
{
    public function testSequentialAwait()
    {
        delay(1);
        delay(1);
    }
}
