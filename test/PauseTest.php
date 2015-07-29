<?php

namespace Amp\Test;

use Amp\Pause;
use Amp\NativeReactor;

class PauseTest extends \PHPUnit_Framework_TestCase {
    protected function setUp() {
        \Amp\reactor($assign = new NativeReactor);
    }

    /**
     * @dataProvider provideBadMillisecondArgs
     * @expectedException \DomainException
     * @expectedExceptionMessage Pause timeout must be greater than or equal to 1 millisecond
     */
    public function testCtorThrowsOnBadMillisecondParam($arg) {
        \Amp\run(function () use ($arg) {
            new Pause($arg);
        });
    }

    public function provideBadMillisecondArgs() {
        return [
            [0],
            [-1],
        ];
    }

    public function testPauseYield() {
        $endReached = false;
        \Amp\run(function () use (&$endReached) {
            $result = (yield new Pause(1));
            $this->assertNull($result);
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }
}
