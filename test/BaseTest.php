<?php

namespace Amp\Test;

abstract class BaseTest extends \PHPUnit_Framework_TestCase {
    protected function setUp() {
        \Amp\reactor($assign = new \Amp\NativeReactor);
    }
}
