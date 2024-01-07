<?php declare(strict_types=1);

namespace Amp;

class PriorityQueueTest extends TestCase
{
    public function provideTestValues(): iterable
    {
        return [
            [100, 0, 0],
            [100, 0, 10],
            [100, 10, 0],
            [100, 10, 10],
            [1000, 25, 25],
            [1000, 100, 100],
            [10, 0, 0],
            [10, 3, 3],
            [5, 1, 2],
        ];
    }

    /**
     * @dataProvider provideTestValues
     */
    public function testOrdering(int $count, int $toRemove, $toIncrement): void
    {
        $priorities = \range(0, $count - 1);
        \shuffle($priorities);

        $queue = new PriorityQueue();

        foreach ($priorities as $key => $priority) {
            $queue->insert($key, $priority);
        }

        for ($i = 0; $i < $toIncrement; ++$i) {
            $index = \random_int(0, $count - 1);
            $queue->insert($index, $count + $i);
            $priorities[$index] = $count + $i;
        }

        $i = 0;
        while ($i < $toRemove) {
            $index = \random_int(0, $count - 1);
            if (!isset($priorities[$index])) {
                continue;
            }

            unset($priorities[$index]);
            $queue->remove($index);
            ++$i;
        }

        $output = [];
        while (($extracted = $queue->extract()) !== null) {
            $output[] = $extracted;
        }

        \asort($priorities);

        self::assertCount(\count($priorities), $output);
        self::assertSame(\array_keys($priorities), $output);
    }
}
