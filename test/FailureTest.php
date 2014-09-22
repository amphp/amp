<?php

namespace AlertTest;

use Alert\Failure;

class FailureTest extends \PHPUnit_Framework_TestCase {

    public function testWhenInvokedImmediately() {
        $exception = new \Exception('test');
        $failure = new Failure($exception);
        $failure->when(function($error, $result) use ($exception) {
            $this->assertNull($result);
            $this->assertSame($exception, $error);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage test
     */
    public function testWaitThrowsImmediately() {
        $exception = new \RuntimeException('test');
        $failure = new Failure($exception);
        $failure->wait();
    }
}
