<?php

namespace Amp\Test;

use Interop\Async\Promise\ErrorHandler;

class Promise {
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PromiseTest extends \Interop\Async\Promise\Test {
    /** @var callable|null */
    private $handler;
    
    function setUp() {
        // Set error handler to null and store previous handler.
        $this->handler = ErrorHandler::set();
        parent::setUp();
    }
    
    function tearDown() {
        // Restore original error handler.
        ErrorHandler::set($this->handler);
        parent::tearDown();
    }
    
    function promise() {
        $promise = new Promise;
        return [
            $promise,
            [$promise, 'resolve'],
            [$promise, 'fail'],
        ];
    }
}
