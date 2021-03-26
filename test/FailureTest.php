<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

class FailureTest extends AsyncTestCase
{
    public function testOnResolve(): void
    {
        $exception = new \Exception;

        $failure = new Failure($exception);

        try {
            await($failure);
        } catch (\Exception $reason) {
            self::assertSame($exception, $reason);
            return;
        }

        self::fail("Promise was not failed");
    }
}
