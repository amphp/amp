<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use Amp\Promise;
use Amp\Success;

class PipelineSourceTest extends AsyncTestCase
{
    /** @var PipelineSource */
    private $source;

    public function setUp()
    {
        parent::setUp();
        $this->source = new PipelineSource;
    }

    public function testEmit()
    {
        $value = 'Emited Value';

        $promise = $this->source->emit($value);
        $pipeline = $this->source->pipe();

        $this->assertSame($value, yield $pipeline->continue());

        $continue = $pipeline->continue(); // Promise will not resolve until another value is emitted or pipeline completed.

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

        $pipeline = $this->source->pipe();

        try {
            yield $pipeline->continue();
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
        $this->expectExceptionMessage('Pipelines cannot emit values after calling complete');

        $this->source->complete();
        $this->source->emit(1);
    }

    /**
     * @depends testEmit
     */
    public function testEmittingNull()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Pipelines cannot emit NULL');

        $this->source->emit(null);
    }

    /**
     * @depends testEmit
     */
    public function testEmittingPromise()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Pipelines cannot emit promises');

        $this->source->emit(new Success);
    }

    public function testDoubleComplete()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->complete();
        $this->source->complete();
    }

    public function testDoubleFail()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->fail(new \Exception);
        $this->source->fail(new \Exception);
    }

    public function testDoubleStart()
    {
        $pipeline = $this->source->pipe();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('A pipeline may be started only once');

        $pipeline = $this->source->pipe();
    }

    public function testEmitAfterContinue()
    {
        $value = 'Emited Value';

        $pipeline = $this->source->pipe();

        $promise = $pipeline->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $backPressure = $this->source->emit($value);

        $this->assertSame($value, yield $promise);

        $pipeline->continue();

        $this->assertNull(yield $backPressure);
    }

    public function testContinueAfterComplete()
    {
        $pipeline = $this->source->pipe();

        $this->source->complete();

        $promise = $pipeline->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->assertNull(yield $promise);
    }

    public function testContinueAfterFail()
    {
        $pipeline = $this->source->pipe();

        $this->source->fail(new \Exception('Pipeline failed'));

        $promise = $pipeline->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pipeline failed');

        yield $promise;
    }


    public function testCompleteAfterContinue()
    {
        $pipeline = $this->source->pipe();

        $promise = $pipeline->continue();
        $this->assertInstanceOf(Promise::class, $promise);

        $this->source->complete();

        $this->assertNull(yield $promise);
    }

    public function testDestroyingPipelineRelievesBackPressure()
    {
        $pipeline = $this->source->pipe();

        $invoked = 0;
        $onResolved = function () use (&$invoked) {
            $invoked++;
        };

        foreach (\range(1, 5) as $value) {
            $promise = $this->source->emit($value);
            $promise->onResolve($onResolved);
        }

        $this->assertSame(0, $invoked);

        unset($pipeline); // Should relieve all back-pressure.

        $this->assertSame(5, $invoked);

        $this->source->complete(); // Should not throw.

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->complete(); // Should throw.
    }

    public function testOnDisposal()
    {
        $invoked = false;
        $this->source->onDisposal(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertFalse($invoked);

        $pipeline = $this->source->pipe();
        $pipeline->dispose();

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

        $pipeline = $this->source->pipe();
        $pipeline->dispose();

        $this->assertFalse($invoked);

        $this->source->onDisposal($this->createCallback(0));
    }

    public function testEmitAfterDisposal()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The pipeline has been disposed');

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        $pipeline->dispose();
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }


    public function testEmitAfterDestruct()
    {
        $this->expectException(DisposedException::class);
        $this->expectExceptionMessage('The pipeline has been disposed');

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline);
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(yield $promise);
        yield $this->source->emit(1);
    }

    public function testFailWithDisposedException()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot fail a pipeline with an instance of');

        $this->source->fail(new DisposedException);
    }
}
