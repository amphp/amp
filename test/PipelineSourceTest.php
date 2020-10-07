<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use Amp\Promise;
use Amp\Success;
use function Amp\async;
use function Amp\await;
use function Amp\delay;

class PipelineSourceTest extends AsyncTestCase
{
    /** @var PipelineSource */
    private PipelineSource $source;

    public function setUp(): void
    {
        parent::setUp();
        $this->source = new PipelineSource;
    }

    public function testEmit(): void
    {
        $value = 'Emited Value';

        $promise = $this->source->emit($value);
        $pipeline = $this->source->pipe();

        $this->assertSame($value, $pipeline->continue());

        $continue = async(fn () => $pipeline->continue()); // Promise will not resolve until another value is emitted or pipeline completed.

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertNull(await($promise));

        $this->assertFalse($this->source->isComplete());

        $this->source->complete();

        $this->assertTrue($this->source->isComplete());

        $this->assertNull(await($continue));
    }

    public function testFail(): void
    {
        $this->assertFalse($this->source->isComplete());
        $this->source->fail($exception = new \Exception);
        $this->assertTrue($this->source->isComplete());

        $pipeline = $this->source->pipe();

        try {
            $pipeline->continue();
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }

    /**
     * @depends testEmit
     */
    public function testEmitAfterComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipelines cannot emit values after calling complete');

        $this->source->complete();
        $this->source->emit(1);
    }

    /**
     * @depends testEmit
     */
    public function testEmittingNull(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Pipelines cannot emit NULL');

        $this->source->emit(null);
    }

    /**
     * @depends testEmit
     */
    public function testEmittingPromise(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Pipelines cannot emit promises');

        $this->source->emit(new Success);
    }

    public function testDoubleComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->complete();
        $this->source->complete();
    }

    public function testDoubleFail(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->fail(new \Exception);
        $this->source->fail(new \Exception);
    }

    public function testDoubleStart(): void
    {
        $pipeline = $this->source->pipe();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('A pipeline may be started only once');

        $pipeline = $this->source->pipe();
    }

    public function testEmitAfterContinue(): void
    {
        $value = 'Emited Value';

        $pipeline = $this->source->pipe();

        $promise = async(fn () => $pipeline->continue());

        $backPressure = $this->source->emit($value);

        $this->assertSame($value, await($promise));

        $promise = async(fn () => $pipeline->continue());

        $this->assertNull(await($backPressure));

        $this->source->complete();
    }

    public function testContinueAfterComplete(): void
    {
        $pipeline = $this->source->pipe();

        $this->source->complete();

        $this->assertNull($pipeline->continue());
    }

    public function testContinueAfterFail(): void
    {
        $pipeline = $this->source->pipe();

        $this->source->fail(new \Exception('Pipeline failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pipeline failed');

        $pipeline->continue();
    }


    public function testCompleteAfterContinue(): void
    {
        $pipeline = $this->source->pipe();

        $promise = async(fn () => $pipeline->continue());
        $this->assertInstanceOf(Promise::class, $promise);

        $this->source->complete();

        $this->assertNull(await($promise));
    }

    public function testDestroyingPipelineRelievesBackPressure(): void
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

        delay(0); // Tick event loop to invoke promise callbacks.

        $this->assertSame(5, $invoked);

        $this->source->complete(); // Should not throw.

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->complete(); // Should throw.
    }

    public function testOnDisposal(): void
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

    public function testOnDisposalAfterCompletion(): void
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

    public function testEmitAfterDisposal(): void
    {
        $this->expectException(DisposedException::class);

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        $pipeline->dispose();
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(await($promise));
        await($this->source->emit(1));
    }

    public function testEmitAfterAutomaticDisposal(): void
    {
        $this->expectException(DisposedException::class);

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(await($promise));
        await($this->source->emit(1));
    }

    public function testEmitAfterAutomaticDisposalWithPendingContinuePromise(): void
    {
        $pipeline = $this->source->pipe();
        $promise = async(fn () => $pipeline->continue());
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        $this->assertFalse($this->source->isDisposed());
        $this->source->emit(1);
        $this->assertSame(1, await($promise));

        $this->assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        await($this->source->emit(2));
    }

    public function testEmitAfterExplicitDisposalWithPendingContinuePromise(): void
    {
        $pipeline = $this->source->pipe();
        $promise = async(fn () => $pipeline->continue());
        $this->source->onDisposal($this->createCallback(1));
        $pipeline->dispose();
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        $this->assertSame(1, await($promise));
    }

    public function testEmitAfterDestruct(): void
    {
        $this->expectException(DisposedException::class);

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline);
        $this->source->onDisposal($this->createCallback(1));
        $this->assertTrue($this->source->isDisposed());
        $this->assertNull(await($promise));
        await($this->source->emit(1));
    }

    public function testFailWithDisposedException(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot fail a pipeline with an instance of');

        $this->source->fail(new DisposedException);
    }
}
