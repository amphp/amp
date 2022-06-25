<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;
use Revolt\EventLoop;

class WeakClosureTest extends AsyncTestCase
{
    public function provideObjectFactories(): iterable
    {
        yield 'binding' => [
            fn (&$count, &$id) => new class($count, $id) {
                private string $callbackId;

                public function __construct(int &$count, &$id)
                {
                    $this->callbackId = $id = EventLoop::repeat(
                        0.001,
                        weakClosure(function (string $callbackId) use (&$count): void {
                            AsyncTestCase::assertNotNull($this);
                            AsyncTestCase::assertStringContainsString('anonymous', static::class);
                            AsyncTestCase::assertSame($callbackId, $this->callbackId);
                            ++$count;
                        })
                    );
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->callbackId);
                }
            },
        ];

        yield 'static' => [
            fn (&$count, &$id) => new class($count, $id) {
                private string $callbackId = '';

                public function __construct(int &$count, &$id)
                {
                    $callbackIdRef = &$this->callbackId;
                    $this->callbackId = $id = EventLoop::repeat(0.001, weakClosure(static function (string $callbackId) use (
                        &$count,
                        &$callbackIdRef
                    ): void {
                        AsyncTestCase::assertSame($callbackId, $callbackIdRef);
                        ++$count;
                    }));
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->callbackId);
                }
            },
        ];

        yield 'fromCallable' => [
            fn (&$count, &$id) => new class($count, $id) {
                private string $callbackId = '';
                private int $count;

                public function __construct(int &$count, &$id)
                {
                    $this->count = &$count;
                    $this->callbackId = $id = EventLoop::repeat(
                        0.001,
                        weakClosure(\Closure::fromCallable([$this, 'callback']))
                    );
                }

                private function callback(string $callbackId): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', static::class);
                    AsyncTestCase::assertSame($callbackId, $this->callbackId);
                    ++$this->count;
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->callbackId);
                }
            },
        ];

        yield '__invoke' => [
            fn (&$count, &$id) => new class($count, $id) {
                private string $callbackId = '';
                private int $count;

                public function __construct(int &$count, &$id)
                {
                    $this->count = &$count;
                    $this->callbackId = $id = EventLoop::repeat(0.001, weakClosure(\Closure::fromCallable($this)));
                }

                public function __invoke(string $callbackId): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', static::class);
                    AsyncTestCase::assertSame($callbackId, $this->callbackId);
                    ++$this->count;
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->callbackId);
                }
            },
        ];
    }

    /**
     * @dataProvider provideObjectFactories
     */
    public function test(callable $factory): void
    {
        $this->setTimeout(0.2);
        $count = 0;
        $id = null;

        $object = $factory($count, $id);

        delay(0.05);
        self::assertGreaterThan(1, $count);

        // Ensure the callback isn't cancelled, yet
        EventLoop::enable($id);

        unset($object); // Should destroy object and cancel loop watcher.

        // Ensure the callback is already cancelled
        $this->expectException(EventLoop\InvalidCallbackError::class);
        $this->expectExceptionCode(EventLoop\InvalidCallbackError::E_INVALID_IDENTIFIER);

        EventLoop::enable($id);
    }

    public function testInheritance(): void
    {
        $object = new WeakClosureTestChild();
        $this->assertSame(WeakClosureTestParent::class, weakClosure($object->getClosure())());
        $this->assertSame(WeakClosureTestParent::class, weakClosure($object->getFirstClassClosure())());
    }
}

class WeakClosureTestParent
{
    private function privateScoped(): string
    {
        return __CLASS__;
    }

    public function getFirstClassClosure(): \Closure
    {
        return $this->privateScoped(...);
    }

    public function getClosure(): \Closure
    {
        return fn () => $this->privateScoped();
    }
}

class WeakClosureTestChild extends WeakClosureTestParent
{
    private function privateScoped(): string
    {
        return __CLASS__;
    }
}
