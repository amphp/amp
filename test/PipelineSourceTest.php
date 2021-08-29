<?php

namespace Amp\Test;

use Amp\Future;
use Amp\DisposedException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PipelineSource;
use function Amp\Future\spawn;
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

        $future = $this->source->emit($value);
        $pipeline = $this->source->pipe();

        self::assertSame($value, $pipeline->continue());

        $continue = spawn(fn (
        ) => $pipeline->continue()); // Promise will not resolve until another value is emitted or pipeline completed.

        self::assertInstanceOf(Future::class, $future);
        self::assertNull($future->join());

        self::assertFalse($this->source->isComplete());

        $this->source->complete();

        self::assertTrue($this->source->isComplete());

        self::assertNull($continue->join());
    }

    public function testFail(): void
    {
        self::assertFalse($this->source->isComplete());
        $this->source->error($exception = new \Exception);
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
    public function testEmittingFuture(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Pipelines cannot emit futures');

        $this->source->emit(Future::complete(null));
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

        $this->source->error(new \Exception);
        $this->source->error(new \Exception);
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

        $future = spawn(fn () => $pipeline->continue());

        $backPressure = $this->source->emit($value);

        self::assertSame($value, $future->join());

        $future = spawn(fn () => $pipeline->continue());

        self::assertNull($backPressure->join());

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

        $this->source->error(new \Exception('Pipeline failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pipeline failed');

        $pipeline->continue();
    }

    public function testCompleteAfterContinue(): void
    {
        $pipeline = $this->source->pipe();

        $future = spawn(fn () => $pipeline->continue());
        self::assertInstanceOf(Future::class, $future);

        $this->source->complete();

        self::assertNull($future->join());
    }

    public function testDestroyingPipelineRelievesBackPressure(): void
    {
        $pipeline = $this->source->pipe();

        $invoked = 0;
        foreach (\range(1, 5) as $value) {
            $future = $this->source->emit($value);
            defer(function () use (&$invoked, $future): void {
                try {
                    $future->join();
                } catch (DisposedException $exception) {
                    // Ignore disposal.
                } finally {
                    $invoked++;
                }
            });
        }

        self::assertSame(0, $invoked);

        unset($pipeline); // Should relieve all back-pressure.

        delay(0.005); // Tick event loop to invoke future callbacks.

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

        $this->source->emit(1)->join();
    }

    public function testEmitAfterAutomaticDisposal(): void
    {
        $pipeline = $this->source->pipe();
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        $this->source->emit(1)->join();
    }

    public function testEmitAfterAutomaticDisposalAfterDelay(): void
    {
        $pipeline = $this->source->pipe();
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        delay(0.01);

        $this->expectException(DisposedException::class);

        $this->source->emit(1)->join();
    }

    public function testEmitAfterAutomaticDisposalWithPendingContinueFuture(): void
    {
        $pipeline = $this->source->pipe();
        $future = spawn(fn () => $pipeline->continue());
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline); // Trigger automatic disposal.
        $this->source->onDisposal($this->createCallback(1));
        self::assertFalse($this->source->isDisposed());
        $this->source->emit(1);
        self::assertSame(1, $future->join());

        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        $this->source->emit(2)->join();
    }

    public function testEmitAfterExplicitDisposalWithPendingContinueFuture(): void
    {
        $pipeline = $this->source->pipe();
        $future = spawn(fn () => $pipeline->continue());
        $this->source->onDisposal($this->createCallback(1));
        $pipeline->dispose();
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());

        $this->expectException(DisposedException::class);

        self::assertSame(1, $future->join());
    }

    public function testEmitAfterDestruct(): void
    {
        $this->expectException(DisposedException::class);

        $pipeline = $this->source->pipe();
        $future = $this->source->emit(1);
        $this->source->onDisposal($this->createCallback(1));
        unset($pipeline);
        $this->source->onDisposal($this->createCallback(1));
        self::assertTrue($this->source->isDisposed());
        self::assertNull($future->join());
        $this->source->emit(1)->join();
    }

    public function testFailWithDisposedException(): void
    {
        // Using DisposedException, but should be treated as fail, not disposal.
        $this->source->error(new DisposedException);

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
                $this->source->error($exception);
            }
        });

        $values = [];

        foreach ($this->source->pipe() as $value) {
            $values[] = $value;
        }

        self::assertSame([1, 2, 3], $values);
    }
}
