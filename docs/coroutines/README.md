---
layout: "docs"
title: "Coroutines"
permalink: "/coroutines/"
---
Coroutines are interruptible functions. In PHP they can be implemented using [Fibers](https://wiki.php.net/rfc/fibers).

{:.note}
> Previous versions of Amp used Generators for a similar purpose, but fibers can be interrupted anywhere in the stack making previous helpers like `Amp\call()` unnecessary.

At any given time, only one fiber is running. When a coroutine suspends, execution of the coroutine is temporarily
interrupted, allowing other tasks to be run. Execution is resumed once a timer expires, stream operations are possible,
or any awaited `Future` completes.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Revolt\EventLoop;

$suspension = EventLoop::createSuspension();

EventLoop::delay(5, function () use ($suspension): void {
    print '++ Executing callback created by EventLoop::delay()' . PHP_EOL;

    $suspension->resume(null);
});

print '++ Suspending to event loop...' . PHP_EOL;

$suspension->suspend();

print '++ Script end' . PHP_EOL;
```

Suspending and resuming fibers is handled by the `Suspension` API of [Revolt](https://revolt.run).

{:.note}
> TODO: Note about futures on public APIs vs. awaiting them internally.
