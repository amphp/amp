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
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise was not failed");
    }
}
