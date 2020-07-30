<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\StreamSource;
use Amp\Success;

class StreamSourceTest extends AsyncTestCase
{
    /** @var StreamSource */
    private $source;

    public function setUp()
    {
        parent::setUp();
        $this->source = new StreamSource;
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

        $this->assertFalse($this->source->isComplete());

        $this->source->complete();

        $this->assertTrue($this->source->isComplete());

        $this->assertNull(yield $continue);
    }

    public function testFail()
    {
        $this->assertFalse($this->source->isComplete());
        $this->source->fail($exception = new \Exception);
        $this->assertTrue($this->source->isComplete());

        $stream = $this->source->stream();

        try {
            yield $stream->continue();
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
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
    public function testEmittingNull()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Streams cannot emit NULL');

        $this->source->emit(null);
    }

    /**
     * @depends testEmit
     */
    public function testEmittingPromise()
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

    public function testOnDisposal()
    {
        $invoked = false;
        $this->source->onDisposal(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertFalse($invoked);

        $stream = $this->source->stream();
        $stream->dispose();

        $this->assertTrue($invoked);

        $this->source->onDisposal($this->createCallback(1));
    }

    public function testOnDisposalAfterCompletion()
    {
        $invoked = false;
        $this->source->onDisposal(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertFalse($invoked);

        $this->source->complete();

        $stream = $this->source->stream();
        $stream->dispose();

        $this->assertFalse($invoked);

        $this->source->onDisposal($this->createCallback(0));
    }

    public function testEmitAfterDisposal()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        $this->source->onCompletion($this->createCallback(0));
        $stream->dispose();
        $this->source->onDisposal($this->createCallback(1));
        $this->source->onCompletion($this->createCallback(0));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }


    public function testEmitAfterDestruct()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The stream has been disposed');

        $stream = $this->source->stream();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        $this->source->onCompletion($this->createCallback(0));
        unset($stream);
        $this->source->onDisposal($this->createCallback(1));
        $this->source->onCompletion($this->createCallback(0));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }

    public function testOnCompletionWithSuccessfulStream()
    {
        $invoked = false;
        $this->source->onCompletion(function (\Throwable $exception = null) use (&$invoked) {
            $this->assertNull($exception);
            $invoked = true;
        });

        $this->source->onDisposal($this->createCallback(0));

        $this->assertFalse($invoked);

        $this->source->complete();

        $this->assertTrue($invoked);

        $this->source->onCompletion($this->createCallback(1, function (\Throwable $exception = null) {
            $this->assertNull($exception);
        }));
    }

    public function testOnCompletionWithFailedStream()
    {
        $reason = new \Exception;
        $invoked = false;
        $this->source->onCompletion(function (\Throwable $exception = null) use (&$invoked, $reason) {
            $this->assertSame($reason, $exception);
            $invoked = true;
        });

        $this->source->onDisposal($this->createCallback(0));

        $this->assertFalse($invoked);

        $this->source->fail($reason);

        $this->assertTrue($invoked);

        $this->source->onCompletion($this->createCallback(1, function (\Throwable $exception = null) use ($reason) {
            $this->assertSame($reason, $exception);
        }));
    }

    public function testFailWithDisposedException()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot fail a stream with an instance of');

        $this->source->fail(new DisposedException);
    }
}
