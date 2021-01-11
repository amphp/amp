<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use function Amp\Promise\wait;

abstract class BaseTest extends TestCase
{
    public function tearDown(): void
    {
        parent::tearDown();

        Loop::setErrorHandler();
        $this->clearLoopRethrows();
    }

    private function clearLoopRethrows()
    {
        $errors = [];

        retry:

        try {
            wait(new Delayed(0));
        } catch (\Throwable $e) {
            $errors[] = (string) $e;

            goto retry;
        }

        if ($errors) {
            \set_error_handler(null);
            \trigger_error(\implode("\n", $errors), E_USER_ERROR);
        }

        $info = Loop::getInfo();
        if ($info['enabled_watchers']['referenced'] + $info['enabled_watchers']['unreferenced'] > 0) {
            \set_error_handler(null);
            \trigger_error("Found enabled watchers on test end: " . \json_encode($info, \JSON_PRETTY_PRINT),
                E_USER_ERROR);
        }
    }
}
