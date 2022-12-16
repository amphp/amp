<?php

namespace Amp;

class TestCase extends \PHPUnit\Framework\TestCase
{
    final protected function createCallback(
        int $invocationCount,
        ?callable $returnCallback = null,
        array $expectArgs = [],
    ): \Closure {
        $mock = $this->createMock(CallbackStub::class);
        $invocationMocker = $mock->expects(self::exactly($invocationCount))
            ->method('__invoke');

        if ($returnCallback) {
            $invocationMocker->willReturnCallback($returnCallback);
        }

        if ($expectArgs) {
            $invocationMocker->with(...$expectArgs);
        }

        return $mock(...);
    }
}
