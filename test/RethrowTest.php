<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\PHPUnit\TestException;
use Concurrent\Deferred;
use function Amp\delay;
use function Amp\rethrow;

class RethrowTest extends TestCase
{
    public function testRethrow(): void
    {
        $exception = new TestException;

        Loop::setErrorHandler($this->createCallback(1));

        rethrow(Deferred::error($exception));
        delay(1);
    }
}
