<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\Pause;
use Interop\Async\Loop;

class IntervalTest extends \PHPUnit_Framework_TestCase {
    const TIMEOUT = 10;
    
    public function testInterval() {
        $count = 3;
        $observable = Amp\interval(self::TIMEOUT, $count);
        
        $i = 0;
        $observable = Amp\each($observable, function ($value) use (&$i) {
            $this->assertSame(++$i, $value);
        });
        
        Amp\wait($observable);
        
        $this->assertSame($count, $i);
    }
    
    /**
     * @depends testInterval
     */
    public function testSlowConsumer() {
        $invoked = 0;
        $count = 5;
        Loop::execute(function () use (&$invoked, $count) {
            $observable = Amp\interval(self::TIMEOUT, $count);
            
            $observable->subscribe(function () use (&$invoked) {
                ++$invoked;
                return new Pause(self::TIMEOUT * 2);
            });
        });
        
        $this->assertSame($count, $invoked);
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage The number of times to emit must be a positive value
     */
    public function testInvalidCount() {
        $observable = Amp\interval(self::TIMEOUT, -1);
    }
}
