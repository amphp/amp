<?php

namespace Amp\Test;

use Amp\Failure;

class FailureTest extends \PHPUnit_Framework_TestCase {

    public function testWhenInvokedImmediately() {
        $exception = new \Exception('test');
        $failure = new Failure($exception);
        $failure->when(function($error, $result) use ($exception) {
            $this->assertNull($result);
            $this->assertSame($exception, $error);
        });
    }
}
