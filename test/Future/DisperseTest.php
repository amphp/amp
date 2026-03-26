<?php declare(strict_types=1);

namespace Amp;

use PHPUnit\Framework\TestCase;

class DisperseTest extends TestCase
{
    public function testTwoComplete(): void
    {
        self::assertSame([1, 2], disperse([
            fn () => 1,
            fn () => 2,
        ]));
    }

    public function testCompletionOrder(): void
    {
        $result = disperse([
            'slow' => function (): string {
                delay(0.05);
                return 'slow';
            },
            'fast' => function (): string {
                delay(0.01);
                return 'fast';
            },
        ]);

        self::assertSame([
            'slow' => 'slow',
            'fast' => 'fast',
        ], $result);
    }

    public function testNonClosure(): void
    {
        $this->expectException(\TypeError::class);

        disperse(['not-a-closure']);
    }

    public function testErrors(): void
    {
        try {
            disperse([
                fn () => 1,
                fn () => throw new \Exception('boom'),
            ]);

            $this->fail('The code should have thrown');
        } catch (CompositeException $e) {
        }

        $reasons = $e->getReasons();
        self::assertCount(1, $reasons);

        self::assertSame('boom', $reasons[1]->getMessage());
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);

        disperse([
            function (): int {
                delay(0.05);
                return 1;
            },
            function (): int {
                delay(0.05);
                return 2;
            },
        ], new TimeoutCancellation(0.01));
    }

    public function testEmpty(): void
    {
        $this->assertSame([], disperse([]));
    }
}
