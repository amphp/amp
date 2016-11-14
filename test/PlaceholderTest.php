<?php declare(strict_types = 1);

namespace Amp\Test;

use Interop\Async\{ Loop, Promise };

class Placeholder {
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PlaceholderTest extends \PHPUnit_Framework_TestCase {
    /** @var \Amp\Test\Placeholder */
    private $placeholder;

    public function setUp() {
        $this->placeholder = new Placeholder;
    }

    public function testWhenOnSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->when($callback);

        $this->placeholder->resolve($value);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testMultipleWhensOnSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->when($callback);
        $this->placeholder->when($callback);
        $this->placeholder->when($callback);

        $this->placeholder->resolve($value);

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenAfterSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenAfterSuccess
     */
    public function testMultipleWhenAfterSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->when($callback);
        $this->placeholder->when($callback);
        $this->placeholder->when($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerOnSuccess() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->when($callback);

            $this->placeholder->resolve($expected);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testWhenAfterSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerAfterSuccess() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->resolve($expected);

            $this->placeholder->when($callback);
        });

        $this->assertSame(1, $invoked);
    }

    public function testWhenOnFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->when($callback);

        $this->placeholder->fail($exception);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnFail
     */
    public function testMultipleWhensOnFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->when($callback);
        $this->placeholder->when($callback);
        $this->placeholder->when($callback);

        $this->placeholder->fail($exception);

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnFail
     */
    public function testWhenAfterFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenAfterFail
     */
    public function testMultipleWhensAfterFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->when($callback);
        $this->placeholder->when($callback);
        $this->placeholder->when($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerOnFail() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->when($callback);

            $this->placeholder->fail(new \Exception);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerAfterFail() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->fail(new \Exception);

            $this->placeholder->when($callback);
        });

        $this->assertSame(1, $invoked);
    }

    public function testResolveWithPromiseBeforeWhen() {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects($this->once())
            ->method("when")
            ->with($this->callback("is_callable"));

        $this->placeholder->resolve($promise);

        $this->placeholder->when(function () {});
    }

    public function testResolveWithPromiseAfterWhen() {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects($this->once())
            ->method("when")
            ->with($this->callback("is_callable"));

        $this->placeholder->when(function () {});

        $this->placeholder->resolve($promise);
    }

    /**
     * @expectedException \Error
     */
    public function testDoubleResolve() {
        $this->placeholder->resolve();
        $this->placeholder->resolve();
    }
}
