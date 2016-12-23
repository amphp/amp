<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp\Deferred;

class WhenOrderTest extends \PHPUnit_Framework_TestCase {
    private $promise3ResolvedSynchronously;
    private $promise4ResolvedSynchronously;

    public function testWhenOrder() {
        $this->expectOutputString("1234");

        $deferred = new Deferred;
        $promise = $deferred->promise();

        $promise->when(function () use ($promise) {
            $promise->when(function () use (&$resolved) {
                $resolved = true;
                printf("%d", 3);
            });

            if ($resolved) {
                $this->promise3ResolvedSynchronously = true;
            }

            printf("%d", 1);
        });

        $promise->when(function () use ($promise) {
            $promise->when(function () use (&$resolved) {
                $resolved = true;
                printf("%d", 4);
            });

            if ($resolved) {
                $this->promise4ResolvedSynchronously = true;
            }

            printf("%d", 2);
        });

        $deferred->resolve();

        $this->assertTrue($this->promise3ResolvedSynchronously);
        $this->assertTrue($this->promise4ResolvedSynchronously);
    }
}
