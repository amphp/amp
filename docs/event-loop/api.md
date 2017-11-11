---
layout: docs
title: Event Loop API
permalink: /event-loop/api
---
This document describes the [`Amp\Loop`](https://github.com/amphp/amp/blob/master/lib/Loop.php) accessor. You might want to also read the documentation contained in the source file, it's extensively documented and doesn't contain much distracting code.

## `run()`

The primary way an application interacts with the event loop is to schedule events for execution and then simply let the program run. Once `Loop::run()` is invoked the event loop will run indefinitely until there are no watchable timer events, IO streams or signals remaining to watch. Long-running programs generally execute entirely inside the confines of a single `Loop::run()` call.

`Loop::run()` accepts an optional callback as first parameter. Passing such a callback is equivalent to calling `Loop::defer($callback)` and `Loop::run()` afterwards.

## `stop()`

The event loop can be stopped at any time while running. When `Loop::stop()` is invoked the event loop will return control to the userland script at the end of the current tick of the event loop. This method may be used to yield control from the event loop even if events or watchable IO streams are still pending.

## Timer Watchers

Amp exposes several ways to schedule timer watchers. Let's look at some details for each function.

### `defer()`

 - Schedules a callback to execute in the next iteration of the event loop
 - This method guarantees a clean call stack to avoid starvation of other events in the current iteration of the loop. An `defer` callback is *always* executed in the next tick of the event loop.
 - After an `defer` timer watcher executes it is automatically garbage collected by the event loop so there is no need for applications to manually cancel the associated watcher.
 - Like all watchers, `defer` timers may be disabled and re-enabled. If you disable this watcher between the time you schedule it and the time that it actually runs the event loop *will not* be able to garbage collect it until it executes. Therefore you must manually cancel an `defer` watcher yourself if it never actually executes to free any associated resources.

**Example**

```php
<?php // using Loop::defer()

use Amp\Loop;

Loop::run(function () {
    echo "line 1\n";
    Loop::defer(function () {
        echo "line 3\n";
    });
    echo "line 2\n";
});
```

**Callback Signature**

`function (string $watcherId, mixed $cbData = null)`

### `delay()`

 - Schedules a callback to execute after a delay of `n` milliseconds
 - A "delay" watcher is also automatically garbage collected by the reactor after execution and applications should not manually cancel it unless they wish to discard the watcher entirely prior to execution.
 - A "delay" watcher that is disabled has its delay time reset so that the original delay time starts again from zero once re-enabled.
 - Like `defer` watchers, a timer scheduled for one-time execution must be manually canceled to free resources if it never runs due to being disabled by the application after creation.

**Example**

```php
<?php // using delay()

use Amp\Loop;

Loop::run(function () {
    // event loop will stop in three seconds
    Loop::delay($msDelay = 3000, "Amp\\Loop::stop");
});
```

**Callback Signature**

`function (string $watcherId, mixed $cbData = null)`

### `repeat()`

 - Schedules a callback to repeatedly execute every `n` milliseconds.
 - Like all other watchers, `repeat` timers may be disabled/re-enabled at any time.
 - Unlike `defer()` and `delay()` watchers, `repeat()` timers must be explicitly canceled to free associated resources. Failure to free `repeat` watchers via `cancel()` once their purpose is fulfilled will result in memory leaks in your application. It is not enough to simply disable repeat watchers as their data is only freed upon cancellation.

```php
<?php // using repeat()

use Amp\Loop;

Loop::run(function () {
    Loop::repeat($msInterval = 100, function ($watcherId) {
        static $i = 0;
        if ($i++ < 3) {
            echo "tick\n";
        } else {
            Loop::cancel($watcherId);
        }
    });
});
```

**Callback Signature**

`function (string $watcherId, mixed $cbData = null)`

## Stream IO Watchers

Stream watchers are how we know when we can read and write to sockets and other streams. These events are how we're able to actually create things like HTTP servers and asynchronous database libraries using the event loop. As such, stream IO watchers form the backbone of any useful non-blocking Amp application.

There are two types of IO watchers:

 - Readability watchers
 - Writability watchers

### `onReadable()`

{:.note}
> This is an advanced low-level API. Most users should use [`amphp/byte-stream`](https://github.com/amphp/byte-stream) instead.

Watchers registered via `Loop::onReadable()` trigger their callbacks in the following situations:

 - When data is available to read on the stream under observation
 - When the stream is at EOF (for sockets, this means the connection is broken)

A common usage pattern for reacting to readable data looks something like this example:

```php
<?php

use Amp\Loop;

const IO_GRANULARITY = 32768;

function isStreamDead($socket) {
    return !is_resource($socket) || @feof($socket);
}

Loop::onReadable($socket, function ($watcherId, $socket) {
    $socketId = (int) $socket;
    $newData = @fread($socket, IO_GRANULARITY);
    if ($newData != "") {
        // There was actually data and not an EOF notification. Let's consume it!
        parseIncrementalData($socketId, $newData);
    } elseif (isStreamDead($socket)) {
        Loop::cancel($watcherId);
    }
});
```

In the above example we've done a few very simple things:

 - Register a readability watcher for a socket that will trigger our callback when there is data available to read.
 - When we read data from the stream in our triggered callback we pass that to a stateful parser that does something domain-specific when certain conditions are met.
 - If the `fread()` call indicates that the socket connection is dead we clean up any resources we've allocated for the storage of this stream. This process should always include calling `Loop::cancel()` on any event loop watchers we registered in relation to the stream.

{:.warning}
> You should always read a multiple of the configured chunk size (default: 8192), otherwise your code might not work as expected with loop backends other than `stream_select()`, see [amphp/amp#65](https://github.com/amphp/amp/issues/65) for more information.

### `onWritable()`

{:.note}
> This is an advanced low-level API. Most users should use [`amphp/byte-stream`](https://github.com/amphp/byte-stream) instead.

 - Streams are essentially *"always"* writable. The only time they aren't is when their respective write buffers are full.

A common usage pattern for reacting to writability involves initializing a writability watcher without enabling it when a client first connects to a server. Once incomplete writes occur we're then able to "unpause" the write watcher using `Loop::enable()` until data is fully sent without having to create and cancel new watcher resources on the same stream multiple times.

## Pausing, Resuming and Canceling Watchers

All watchers, regardless of type, can be temporarily disabled and enabled in addition to being cleared via `Loop::cancel()`. This allows for advanced capabilities such as disabling the acceptance of new socket clients in server applications when simultaneity limits are reached. In general, the performance characteristics of watcher reuse via pause/resume are favorable by comparison to repeatedly canceling and re-registering watchers.

### `disable()`

A simple disable example:

```php
<?php

use Amp\Loop;

// Register a watcher we'll disable
$watcherIdToDisable = Loop::delay($msDelay = 1000, function () {
    echo "I'll never execute in one second because: disable()\n";
});

// Register a watcher to perform the disable() operation
Loop::delay($msDelay = 500, function () use ($watcherIdToDisable) {
    echo "Disabling WatcherId: ", $watcherIdToDisable, "\n";
    Loop::disable($watcherIdToDisable);
});

Loop::run();
```

After our second watcher callback executes the event loop exits because there are no longer any enabled watchers registered to process.

### `enable()`

`enable()` is the diametric analog of the `disable()` example demonstrated above:

```php
<?php

use Amp\Loop;

// Register a watcher
$myWatcherId = Loop::repeat($msInterval = 1000, function() {
    echo "tick\n";
});

// Disable the watcher
Loop::disable($myWatcherId);

// Remember, nothing happens until the event loop runs, so it doesn't matter that we
// previously created and disabled $myWatcherId
Loop::run(function () use ($myWatcherId) {
    // Immediately enable the watcher when the reactor starts
    Loop::enable($myWatcherId);
    // Now that it's enabled we'll see tick output in our console every 1000ms.
});
```

For a slightly more complex use case, let's look at a common scenario where a server might create a write watcher that is initially disabled but subsequently enabled as necessary:

```php
<?php

use Amp\Loop;

class Server {
    private $clients = [];

    public function startServer() {
        // ... server bind and accept logic would exist here
        Loop::run();
    }

    private function onNewClient($sock) {
        $socketId = (int) $sock;
        $client = new ClientStruct;
        $client->socket = $sock;
        $readWatcher = Loop::onReadable($sock, function () use ($client) {
            $this->onReadable($client);
        });
        $writeWatcher = Loop::onWritable($sock, function () use ($client) {
            $this->doWrite($client);
        });

        Loop::disable($writeWatcher); // <-- let's initialize the watcher as "disabled"

        $client->readWatcher = $readWatcher;
        $client->writeWatcher = $writeWatcher;

        $this->clients[$socketId] = $client;
    }

    // ... other class implementation details here ...

    private function writeToClient($client, $data) {
        $client->writeBuffer .= $data;
        $this->doWrite($client);
    }

    private function doWrite(ClientStruct $client) {
        $bytesToWrite = strlen($client->writeBuffer);
        $bytesWritten = @fwrite($client->socket, $client->writeBuffer);

        if ($bytesToWrite === $bytesWritten) {
            Loop::disable($client->writeWatcher);
        } elseif ($bytesWritten >= 0) {
            $client->writeBuffer = substr($client->writeBuffer, $bytesWritten);
            Loop::enable($client->writeWatcher);
        } elseif ($this->isSocketDead($client->socket)) {
            $this->unloadClient($client);
        }
    }

    // ... other class implementation details here ...
}
```

### `cancel()`

It's important to *always* cancel persistent watchers once you're finished with them or you'll create memory leaks in your application. This functionality works in exactly the same way as  the above `enable` / `disable` examples:

```php
<?php

use Amp\Loop;

Loop::run(function() {
    $myWatcherId = Loop::repeat($msInterval = 1000, function () {
        echo "tick\n";
    });

    // Cancel $myWatcherId in five seconds and exit the event loop
    Loop::delay($msDelay = 5000, function () use ($myWatcherId) {
        Loop::cancel($myWatcherId);
    });
});
```

## `onSignal()`

`Loop::onSignal()` can be used to react to signals sent to the process.

```php
<?php

use Amp\Loop;

Loop::run(function () {
    // Let's tick off output once per second so we can see activity.
    Loop::repeat($msInterval = 1000, function () {
            echo "tick: ", date('c'), "\n";
    });

    // What to do when a SIGINT signal is received
    $watcherId = Loop::onSignal(UV::SIGINT, function () {
        echo "Caught SIGINT! exiting ...\n";
        exit;
    });
});
```

As should be clear from the above example, signal watchers may be enabled, disabled and canceled like any other event.

## Referencing Watchers

Watchers can either be referenced or unreferenced. An unreferenced watcher doesn't keep the loop alive. All watchers are referenced by default.

One example to use unreferenced watchers is when using signal watchers. Generally, if all watchers are gone and only the signal watcher still exists, you want to exit the loop as you're not actively waiting for that event to happen.

### `reference()`

Marks a watcher as referenced. Takes the `$watcherId` as first and only argument.

### `unreference()`

Marks a watcher as unreferenced. Takes the `$watcherId` as first and only argument.

## Driver Bound State

Sometimes it's very handy to have global state. While dependencies should usually be injected, it is impracticable to pass a `DnsResolver` into everything that needs a network connection. The `Loop` accessor provides therefore the two methods `getState` and `setState` to store state global to the current event loop driver.

These should be used with care! They can be used to store loop bound singletons such as the DNS resolver, filesystem driver, or global `ReactAdapter`. Applications should generally not use these methods.

## Event Loop Addenda

### Watcher Callback Parameters

Watcher callbacks are invoked using the following standardized parameter order:

| Watcher Type            | Callback Signature                                    |
| ----------------------- | ------------------------------------------------------|
| `defer()`               | `function(string $watcherId, $callbackData)`          |
| `delay()`               | `function(string $watcherId, $callbackData)`          |
| `repeat()`              | `function(string $watcherId, $callbackData)`          |
| `onReadable()`          | `function(string $watcherId, $stream, $callbackData)` |
| `onWritable()`          | `function(string $watcherId, $stream, $callbackData)` |
| `onSignal()`            | `function(string $watcherId, $signo, $callbackData)`  |


### Watcher Cancellation Safety

It is always safe to cancel a watcher from within its own callback. For example:

```php
<?php

use Amp\Loop;

$increment = 0;

Loop::repeat($msDelay = 50, function ($watcherId) use (&$increment) {
    echo "tick\n";
    if (++$increment >= 3) {
        Loop::cancel($watcherId); // <-- cancel myself!
    }
});
```

It is also always safe to cancel a watcher from multiple places. A double-cancel will simply be ignored.

### An Important Note on Writability

Because streams are essentially *"always"* writable you should only enable writability watchers while you have data to send. If you leave these watchers enabled when your application doesn't have anything to write the watcher will trigger endlessly until disabled or canceled. This will max out your CPU. If you're seeing inexplicably high CPU usage in your application it's a good bet you've got a writability watcher that you failed to disable or cancel after you were finished with it.

A standard pattern in this area is to initialize writability watchers in a disabled state before subsequently enabling them at a later time as shown here:

```php
<?php

use Amp\Loop;

$watcherId = Loop::onWritable(STDOUT, function () {});
Loop::disable($watcherId);
// ...
Loop::enable($watcherId);
// ...
Loop::disable($watcherId);
```

### Process Signal Number Availability

`php-uv` exposes `UV::SIG*` constants for watchable signals. Applications using the `EventDriver` will need to manually specify the appropriate integer signal numbers when registering signal watchers.

[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"

### Timer Drift

Repeat timers are basically simple delay timers that are automatically rescheduled right before the appropriate handler is triggered. They are subject to timer drift. Multiple timers might stack up in case they execute as coroutines.
