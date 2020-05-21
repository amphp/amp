<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\Internal\YieldSource;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;

class YieldSourceTest extends AsyncTestCase
{
    /** @var YieldSource */
    private $source;

    public function setUp()
    {
        parent::setUp();
        $this->source = new YieldSource;
    }

    public function testYield()
    {
        $value = 'Yielded Value';

        $promise = $this->source->yield($value);
        $stream = $this->source->stream();

        $this->assertSame($value, yield $stream->continue());

        $continue = $stream->continue(); // Promise will not resolve until another value is yielded or stream completed.

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertNull(yield $promise);

        $this->source->complete();

        $this->assertNull(yield $continue);
    }

    /**
     * @depends testYield
     */
    public function testYieldAfterComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Streams cannot yield values after calling complete');

        $this->source->complete();
        $this->source->yield(1);
    }

    /**
     * @depends testYield
     */
    public function testYieldingNull()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Streams cannot yield NULL');

        $this->source->yield(null);
    }

    /**
     * @depends testYield
     */
    public function testYieldingPromise()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Streams cannot yield promises');

        $this->source->yield(new Success);
    }

    public function testDoubleComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->complete();
        $this->source->complete();
    }

    public function testDoubleFail()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->fail(new \Exception);
        $this->source->fail(new \Exception);
    }

    public function testDoubleStart()
    {
        $stream = $this->source->stream();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('A stream may be started only once');

        $stream = $this->source->stream();
    }

    public function testYieldAfterContinue()
    {
        $value = 'Yielded Value';

        $stream = $this->source->stream();

        $promise = $stream->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $backPressure = $this->source->yield($value);

        $this->assertSame($value, yield $promise);

        $stream->continue();

        $this->assertNull(yield $backPressure);
    }

    public function testContinueAfterComplete()
    {
        $stream = $this->source->stream();

        $this->source->complete();

        $promise = $stream->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->assertNull(yield $promise);
    }

    public function testContinueAfterFail()
    {
        $stream = $this->source->stream();

        $this->source->fail(new \Exception('Stream failed'));

        $promise = $stream->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stream failed');

        yield $promise;
    }


    public function testCompleteAfterContinue()
    {
        $stream = $this->source->stream();

        $promise = $stream->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->source->complete();

        $this->assertNull(yield $promise);
    }

    public function testDestroyingStreamRelievesBackPressure()
    {
        $stream = $this->source->stream();

        $invoked = 0;
        $onResolved = function () use (&$invoked) {
            $invoked++;
        };

        foreach (\range(1, 5) as $value) {
            $promise = $this->source->yield($value);
            $promise->onResolve($onResolved);
        }

        $this->assertSame(0, $invoked);

        unset($stream); // Should relieve all back-pressure.

        $this->assertSame(5, $invoked);

        $this->source->complete(); // Should not throw.

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Stream has already been completed');

        $this->source->complete(); // Should throw.
    }

    public function testYieldAfterDisposal()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->yield(1);
        $stream->dispose();
        $this->assertNull(yield $promise);
        yield $this->source->yield(1);
    }


    public function testYieldAfterDestruct()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->yield(1);
        unset($stream);
        $this->assertNull(yield $promise);
        yield $this->source->yield(1);
    }
}
