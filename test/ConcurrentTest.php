<?php declare(strict_types=1);

namespace Amp;

class ConcurrentTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertSame([], \iterator_to_array(concurrent([])));
    }

    public function testReturnsFuturePerClosureWithKeysPreserved(): void
    {
        $closures = ['one' => fn () => 1, 'two' => fn () => 2];

        $futures = \iterator_to_array(concurrent($closures));

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

    public function testGeneratorInput(): void
    {
        $closures = (static function (): \Generator {
            yield 'one' => static fn () => 1;
            yield 'two' => static fn () => 2;
        })();

        self::assertSame(['one' => 1, 'two' => 2], Future\await(concurrent($closures)));
    }

    public function testUnboundedProducer(): void
    {
        $closures = (static function (): \Generator {
            for ($i = 0; ; ++$i) {
                delay(0.01);

                yield static fn (): int => $i;
            }
        })();

        self::assertSame([0, 1], Future\awaitAnyN(2, concurrent($closures)));
    }

    public function testCombinatorConsumesTheEntireConcurrentIterator(): void
    {
        $consumed = 0;

        $closures = (static function () use (&$consumed): \Generator {
            while (++$consumed < 3) {
                yield static fn () => 7;
            }
        })();

        $futures = concurrent($closures);
        self::assertSame(0, $consumed, 'Nothing is consumed till combinator is called');

        self::assertSame(7, Future\awaitFirst($futures));
        self::assertSame(3, $consumed, 'Combinator consumes all the iterations');
    }
}
