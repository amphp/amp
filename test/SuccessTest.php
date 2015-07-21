<?php

namespace Amp\Test;

use Amp\Success;

class SuccessTest extends \PHPUnit_Framework_TestCase {

    public function testDefaultNullResult() {
        $wasInvoked = false;
        $success = new Success;
        $success->when(function ($error, $result) use (&$wasInvoked) {
            $this->assertNull($error);
            $this->assertNull($result);
            $wasInvoked = true;
        });
        $this->assertTrue($wasInvoked);
    }

    /**
     * @dataProvider provideInstantiationArgs
     */
    public function testWhenInvokedImmediately($arg) {
        $wasInvoked = false;
        $success = new Success($arg);
        $success->when(function ($error, $result) use (&$wasInvoked, $arg) {
            $this->assertNull($error);
            $this->assertSame($arg, $result);
            $wasInvoked = true;
        });
        $this->assertTrue($wasInvoked);
    }

    public function provideInstantiationArgs() {
        return [
            [42],
            ["string"],
            [new \StdClass],
        ];
    }

    public function testWhenReturnsSelf() {
        $success = new Success;
        $result = $success->when(function () {});
        $this->assertSame($success, $result);
    }

    public function testWatchReturnsSelf() {
        $success = new Success;
        $result = $success->watch(function () {});
        $this->assertSame($success, $result);
    }
}
