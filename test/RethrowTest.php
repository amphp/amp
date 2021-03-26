<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\delay;

class RethrowTest extends AsyncTestCase
{
    public function testRethrow(): void
    {
        $exception = new \Exception;

        $promise = new Failure($exception);

        Promise\rethrow($promise);

        $invoked = false;
        Loop::setErrorHandler(function (\Throwable $exception) use (&$invoked, &$reason): void {
            $invoked = true;
            $reason = $exception;
        });

        delay(0); // Tick the event loop.

        self::assertTrue($invoked);
        self::assertSame($reason, $exception);
    }
}
