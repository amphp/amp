<?php

namespace Amp\Test;

use Amp\Failure;

class FailureTest extends \PHPUnit_Framework_TestCase {
    public function testWhenInvokedImmediately() {
        $wasInvoked = false;
        $exception = new \Exception("test");
        $failure = new Failure($exception);
        $failure->when(function ($error, $result) use ($exception, &$wasInvoked) {
            $this->assertNull($result);
            $this->assertSame($exception, $error);
            $wasInvoked = true;
        });
        $this->assertTrue($wasInvoked);
    }

    public function testWhenReturnsSelf() {
        $exception = new \Exception("test");
        $failure = new Failure($exception);
        $result = $failure->when(function () {});
        $this->assertSame($failure, $result);
    }

    public function testWatchReturnsSelf() {
        $exception = new \Exception("test");
        $failure = new Failure($exception);
        $result = $failure->watch(function () {});
        $this->assertSame($failure, $result);
    }

    /**
     * @dataProvider provideBadCtorArgs
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Throwable Exception instance required
     */
    public function testCtorThrowsOnNonThrowableParam($arg) {
        $failure = new Failure($arg);
    }

    public function provideBadCtorArgs() {
        return [
            [42],
            ["string"],
            [new \StdClass],
        ];
    }
}
