<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\Internal\EmitSource;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;

class EmitSourceTest extends AsyncTestCase
{
    /** @var EmitSource */
    private $source;

    public function setUp()
    {
        parent::setUp();
        $this->source = new EmitSource;
    }

    public function testEmit()
    {
        $value = 'Emited Value';

        $promise = $this->source->emit($value);
        $stream = $this->source->stream();

        $this->assertSame($value, yield $stream->continue());

        $continue = $stream->continue(); // Promise will not resolve until another value is emitted or stream completed.

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertNull(yield $promise);

        $this->source->complete();

        $this->assertNull(yield $continue);
    }

    /**
     * @depends testEmit
     */
    public function testEmitAfterComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Streams cannot emit values after calling complete');

        $this->source->complete();
        $this->source->emit(1);
    }

    /**
     * @depends testEmit
     */
    public function testEmitingNull()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Streams cannot emit NULL');

        $this->source->emit(null);
    }

    /**
     * @depends testEmit
     */
    public function testEmitingPromise()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Streams cannot emit promises');

        $this->source->emit(new Success);
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

    public function testEmitAfterContinue()
    {
        $value = 'Emited Value';

        $stream = $this->source->stream();

        $promise = $stream->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $backPressure = $this->source->emit($value);

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
            $promise = $this->source->emit($value);
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

    public function testEmitAfterDisposal()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->emit(1);
        $stream->dispose();
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }


    public function testEmitAfterDestruct()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->emit(1);
        unset($stream);
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }
}
