<?php

namespace Amp\Test;

use Amp\Pause;
use Amp\NativeReactor;

class PauseTest extends \PHPUnit_Framework_TestCase {
    /**
     * @dataProvider provideBadMillisecondArgs
     * @expectedException \DomainException
     * @expectedExceptionMessage Pause timeout must be greater than or equal to 1 millisecond
     */
    public function testCtorThrowsOnBadMillisecondParam($arg) {
        (new NativeReactor)->run(function ($reactor) use ($arg) {
            new Pause($arg, $reactor);
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
        (new NativeReactor)->run(function ($reactor) use (&$endReached) {
            $result = (yield new Pause(1, $reactor));
            $this->assertNull($result);
            $endReached = true;
        });
        $this->assertTrue($endReached);
    }
}
