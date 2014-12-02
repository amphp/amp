<?php

namespace Amp\Test;

use Amp\Success;

class SuccessTest extends \PHPUnit_Framework_TestCase {

    public function testWhenInvokedImmediately() {
        $value = 42;
        $success = new Success($value);
        $success->when(function($error, $result) use ($value) {
            $this->assertNull($error);
            $this->assertSame($value, $result);
        });
    }
}
