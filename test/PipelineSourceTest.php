<?php

namespace Amp\Test;

use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use Amp\Promise;
use Amp\Success;
use function Amp\async;
use function Amp\await;
use function Revolt\EventLoop\defer;
use function Revolt\EventLoop\delay;

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

        self::assertSame($value, $pipeline->continue());

        $continue = async(fn (
        ) => $pipeline->continue()); // Promise will not resolve until another value is emitted or pipeline completed.

        self::assertInstanceOf(Promise::class, $promise);
        self::assertNull(await($promise));

        self::assertFalse($this->source->isComplete());

        $this->source->complete();

        self::assertTrue($this->source->isComplete());

        self::assertNull(await($continue));
    }

    public function testFail(): void
    {
        self::assertFalse($this->source->isComplete());
        $this->source->fail($exception = new \Exception);
        self::assertTrue($this->source->isComplete());

        $pipeline = $this->source->pipe();

        try {
            $pipeline->continue();
        } catch (\Exception $caught) {
            self::assertSame($exception, $caught);
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

        self::assertSame($value, await($promise));

        $promise = async(fn () => $pipeline->continue());

        self::assertNull(await($backPressure));

        $this->source->complete();
    }

    public function testContinueAfterComplete(): void
    {
        $pipeline = $this->source->pipe();

        $this->source->complete();

        self::assertNull($pipeline->continue());
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
        self::assertInstanceOf(Promise::class, $promise);

        $this->source->complete();

        self::assertNull(await($promise));
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

        self::assertSame(0, $invoked);

        unset($pipeline); // Should relieve all back-pressure.

        delay(5); // Tick event loop to invoke promise callbacks.

        self::assertSame(5, $invoked);

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

        self::assertFalse($invoked);

        $pipeline = $this->source->pipe();
        $pipeline->dispose();

        delay(0);

        self::assertTrue($invoked);

        $this->source->onDisposal($this->createCallback(1));
    }

    public function testOnDisposalAfterCompletion(): void
    {
        $invoked = false;
        $this->source->onDisposal(function () use (&$invoked) {
            $invoked = true;
        });

        self::assertFalse($invoked);

        $this->source->complete();

        $pipeline = $this->source->pipe();
        $pipeline->dispose();

        self::assertFalse($invoked);

        $this->source->onDisposal($this->createCallback(0));

        delay(0);
    }

    public function testEmitAfterDisposal(): void
    {
        $pipeline = $this->source->pipe();
        $this->source->onDisposal($this->createCallback(1));
        $pipeline->dispose();
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        await($this->source->emit(1));
    }

    public function testEmitAfterAutomaticDisposal(): void
    {
        $pipeline = $this->source->pipe();
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        await($this->source->emit(1));
    }

    public function testEmitAfterAutomaticDisposalAfterDelay(): void
    {
        $pipeline = $this->source->pipe();
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        delay(10);

        $this->expectException(DisposedException::class);

        await($this->source->emit(1));
    }

    public function testEmitAfterAutomaticDisposalWithPendingContinuePromise(): void
    {
        $pipeline = $this->source->pipe();
        $promise = async(fn () => $pipeline->continue());
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertFalse($this->source->isDisposed());
        $this->source->emit(1);
        self::assertSame(1, await($promise));

        self::assertTrue($this->source->isDisposed());

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
        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        self::assertSame(1, await($promise));
    }

    public function testEmitAfterDestruct(): void
    {
        $this->expectException(DisposedException::class);

        $pipeline = $this->source->pipe();
        $promise = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline);
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());
        self::assertNull(await($promise));
        await($this->source->emit(1));
    }

    public function testFailWithDisposedException(): void
    {
        // Using DisposedException, but should be treated as fail, not disposal.
        $this->source->fail(new DisposedException);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Pipeline has already been completed');

        $this->source->complete();
    }

    public function testTraversable(): void
    {
        defer(function (): void {
            try {
                $this->source->yield(1);
                $this->source->yield(2);
                $this->source->yield(3);
                $this->source->complete();
            } catch (\Throwable $exception) {
                $this->source->fail($exception);
            }
        });

        $values = [];

        foreach ($this->source->pipe() as $value) {
            $values[] = $value;
        }

        self::assertSame([1, 2, 3], $values);
    }
}
