<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp\Deferred;

class WhenOrderTest extends \PHPUnit_Framework_TestCase {
    public function testWhenOrder() {
        $this->expectOutputString("123456");

        $deferred = new Deferred;
        $promise = $deferred->promise();

        $promise->when(function () use ($promise) {
            print 1;

            $promise->when(function () {
                print 2;
            });

            print 3;
        });

        $promise->when(function () use ($promise) {
            print 4;

            $promise->when(function () {
                print 5;
            });

            print 6;
        });

        $deferred->resolve();
    }
}
