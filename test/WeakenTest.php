<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;
use Revolt\EventLoop;

class WeakenTest extends AsyncTestCase
{
    public function provideObjectFactories(): iterable
    {
        return [
            'binding' => [fn (&$count) => new class($count) {
                private string $watcher;

                public function __construct(int &$count)
                {
                    $this->watcher = EventLoop::repeat(0.01, weaken(function (string $watcher) use (&$count): void {
                        AsyncTestCase::assertNotNull($this);
                        AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
                        AsyncTestCase::assertSame($watcher, $this->watcher);
                        ++$count;
                    }));
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->watcher);
                }
            }],
            'static' => [fn (&$count) => new class($count) {
                private string $watcher = '';

                public function __construct(int &$count)
                {
                    $watcherRef = &$this->watcher;
                    $this->watcher = EventLoop::repeat(0.01, weaken(static function (string $watcher) use (
                        &$count,
                        &$watcherRef
                    ): void {
                        AsyncTestCase::assertSame($watcher, $watcherRef);
                        ++$count;
                    }));
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->watcher);
                }
            }],
            'fromCallable' => [fn (&$count) => new class($count) {
                private string $watcher = '';
                private int $count;

                public function __construct(int &$count)
                {
                    $this->count = &$count;
                    $this->watcher = EventLoop::repeat(0.01, weaken(\Closure::fromCallable([$this, 'callback'])));
                }

                private function callback(string $watcher): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
                    AsyncTestCase::assertSame($watcher, $this->watcher);
                    ++$this->count;
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->watcher);
                }
            }],
            '__invoke' => [fn (&$count) => new class($count) {
                private string $watcher = '';
                private int $count;

                public function __construct(int &$count)
                {
                    $this->count = &$count;
                    $this->watcher = EventLoop::repeat(0.01, weaken($this));
                }

                public function __invoke(string $watcher): void
                {
                    AsyncTestCase::assertNotNull($this);
                    AsyncTestCase::assertStringContainsString('anonymous', \get_class($this));
                    AsyncTestCase::assertSame($watcher, $this->watcher);
                    ++$this->count;
                }

                public function __destruct()
                {
                    EventLoop::cancel($this->watcher);
                }
            }],
        ];
    }

    /**
     * @dataProvider provideObjectFactories
     */
    public function test(callable $factory): void
    {
        $this->setTimeout(0.1);
        $count = 0;

        $object = $factory($count);

        delay(0.035);
        unset($object); // Should destroy object and cancel loop watcher.
        self::assertSame(3, $count);

        delay(0.025);
        self::assertSame(3, $count);
    }
}
