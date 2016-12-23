<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp\Deferred;

class WhenOrderTest extends \PHPUnit_Framework_TestCase {
    public function testWhenOrder() {
        $this->expectOutputString("1234");

        $deferred = new Deferred;
        $promise = $deferred->promise();

        $promise->when(function () use ($promise) {
            $promise->when(function () {
                printf("%d", 3);
            });
            printf("%d", 1);
        });

        $promise->when(function () use ($promise) {
            $promise->when(function () {
                printf("%d", 4);
            });
            printf("%d", 2);
        });

        $deferred->resolve();
    }
}
