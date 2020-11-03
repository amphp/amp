<?php

namespace Amp\Test\Loop;

use Amp\Loop\Internal\TimerQueue;
use Amp\Loop\Watcher;
use PHPUnit\Framework\TestCase;

class TimerQueueTest extends TestCase
{
    public function testHeapOrder()
    {
        $values = [
            29022197, 29026651, 29026649, 29032037, 29031955, 29032037, 29031870, 29032136, 29032075, 29032144,
            29032160, 29032101, 29032130, 29032091, 29032107, 29032181, 29032137, 29032142, 29032142, 29032146,
            29032158, 29032166, 29032177, 29032181, 29032180, 29032184, 29032193, 29032122
        ];
        $indexToRemove = 16;
        $queue = new TimerQueue;
        $id = 'a';
        $watchers = [];
        foreach ($values as $value) {
            $watcher = new Watcher;
            $watcher->type = Watcher::DELAY;
            $watcher->id = $id++;
            $watcher->callback = static function () {};
            $watcher->expiration = $watcher->value = $value;
            $watchers[] = $watcher;
        }

        $toRemove = $watchers[$indexToRemove];
        foreach ($watchers as $watcher) {
            $queue->insert($watcher);
        }
        $queue->remove($toRemove);

        \array_splice($values, $indexToRemove, 1);
        \sort($values);
        $output = [];
        while (($extracted = $queue->extract(\PHP_INT_MAX)) !== null) {
            $output[] = $extracted->expiration;
        }

        $this->assertSame($values, $output);
    }
}
