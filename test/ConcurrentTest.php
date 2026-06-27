<?php declare(strict_types=1);

namespace Amp;

class ConcurrentTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertSame([], concurrent([]));
    }

    public function testReturnsFuturePerClosureWithKeysPreserved(): void
    {
        $closures = ['one' => fn () => 1, 'two' => fn () => 2];

        $futures = concurrent($closures);

        self::assertContainsOnlyInstancesOf(Future::class, $futures);
        self::assertSame(['one', 'two'], \array_keys($futures));
    }

    public function testClosuresAreEvaluated(): void
    {
        self::assertSame(
            ['one' => 1, 'two' => 2],
            Future\await(concurrent(['one' => fn () => 1, 'two' => fn () => 2]))
        );
    }

    public function testClosuresAreEvaluatedConcurrently(): void
    {
        $order = [];

        $futures = concurrent([
            static function () use (&$order): void {
                delay(0.02);
                $order[] = 'slow';
            },
            static function () use (&$order): void {
                delay(0.01);
                $order[] = 'fast';
            },
        ]);

        Future\await($futures);

        // "fast" is declared second but completes first, proving the closures run concurrently.
        self::assertSame(['fast', 'slow'], $order);
    }
}
