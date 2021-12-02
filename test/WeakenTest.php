<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;
use Revolt\EventLoop;

class WeakenTest extends AsyncTestCase
{
    public function provideObjectFactories(): iterable
    {
        yield 'binding' => [
            fn (&$count) => new class($count) {
                private string $callbackId;

                public function __construct(int &$count)
                {
                    $this->callbackId = EventLoop::repeat(
                        0.001,
                        weaken(function (string $callbackId) use (&$count): void {
                            AsyncTestCase::assertNotNull($this);
                            AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
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
            fn (&$count) => new class($count) {
                private string $callbackId = '';

                public function __construct(int &$count)
                {
                    $callbackIdRef = &$this->callbackId;
                    $this->callbackId = EventLoop::repeat(0.001, weaken(static function (string $callbackId) use (
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
            fn (&$count) => new class($count) {
                private string $callbackId = '';
                private int $count;

                public function __construct(int &$count)
                {
                    $this->count = &$count;
                    $this->callbackId = EventLoop::repeat(0.001, weaken(\Closure::fromCallable([$this, 'callback'])));
                }

                private function callback(string $callbackId): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
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
            fn (&$count) => new class($count) {
                private string $callbackId = '';
                private int $count;

                public function __construct(int &$count)
                {
                    $this->count = &$count;
                    $this->callbackId = EventLoop::repeat(0.001, weaken($this));
                }

                public function __invoke(string $callbackId): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
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

        $object = $factory($count);

        delay(0.05);
        unset($object); // Should destroy object and cancel loop watcher.
        self::assertGreaterThan(1, $count);

        $countBackup = $count;

        delay(0.05);
        self::assertSame($countBackup, $count);
    }
}
