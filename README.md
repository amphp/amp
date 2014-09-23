Amp
=====

Amp is a non-blocking concurrency framework for PHP applications

**Dependencies**

- PHP 5.5+

Optional PHP extensions may be used for improved event loop file descriptor observation performance.
An extension is also necessary if you need to watch for process signals in your application:

- (preferred) [php-uv](https://github.com/chobie/php-uv) for libuv backends.
- [*pecl libevent*][libevent] for libevent backends. Windows libevent extension DLLs are
  available [here][win-libevent]. php-uv is preferred, but libevent is better than nothing.

**Installation**

```bash
$ git clone https://github.com/amphp/amp.git
$ cd amp
$ composer.phar install
```



The Guide
----------------------------------------------------------------------------------------------------

### Using the Event Reactor

[**Event Reactor Concepts**](#event-reactor-concepts)

 - [Reactor Implementations](#reactor-implementations)
 - [Reactor == Task Scheduler](#reactor--task-scheduler)
 - [The Universal Reactor](#the-universal-reactor)

[**Controlling the Reactor**](#controlling-the-reactor)

 - [`run()`](#run)
 - [`tick()`](#tick)
 - [`stop()`](#stop)

[**Timer Watchers**](#timer-watchers)

 - [`immediately()`](#immediately)
 - [`once()`](#once)
 - [`repeat()`](#repeat)
 - [`at()`](#at)

[**Stream IO Watchers**](#stream-io-watchers)

 - [`onReadable()`](#onreadable)
 - [`onWritable()`](#onwritable)
 - [`watchStream()`](#watchstream)

[**Pausing, Resuming and Cancelling Watchers**](#pausing-resuming-and-cancelling-watchers)

 - [`disable()`](#disable)
 - [`enable()`](#enable)
 - [`cancel()`](#cancel)

[**Process Signal Watchers**](#process-signal-watchers)

### Managing Concurrency

 - Futures
 - Promises
 - Functors
 - Generators
 - Avoiding Callback Hell

[**Addenda**](#addenda)

- [Callback Invocation Parameters](#callback-invocation-parameters)
- [Watcher Cancellation Safety](#watcher-cancellation-safety)
- [An Important Note on Writability Watchers](#an-important-note-on-writability)
- [Process Signal Number Availability](#process-signal-number-availability)


----------------------------------------------------------------------------------------------------


## Event Reactor Concepts

#### Reactor Implementations

It may surprise people to learn that the PHP standard library already has everything we need to
write event-driven and non-blocking applications. We only reach the limits of native PHP's
functionality in this area when we ask it to poll several hundred streams for read/write capability
at the same time. Even in this case, though, the fault is not with PHP but the underlying system
`select()` call which is linear in its performance degradation as load increases.

For performance that scales out to high volume we require more advanced capabilities currently
found only in extensions. If you wish to, for example, service 10,000 simultaneous clients in an
Amp-backed socket server you would definitely need to use one of the reactors based on a PHP
extension. However, if you're using Amp in a strictly local program for non-blocking concurrency
or you don't need to handle more than ~100 or so simultaneous clients in a server application the
native PHP functionality is perfectly adequate.

Amp currently exposes three separate implementations for its standard `Reactor` interface. Each
behaves exactly the same way from an external API perspective. The main differences have to do
with underlying performance characteristics. The one capability that the extension-based reactors
*do* offer that's unavailable with the native implementation is the ability to watch for process
control signals. The current implementations are listed here:


| Class                 | Extension                                             |
| --------------------- | ----------------------------------------------------- |
| Amp\NativeReactor   | n/a                                                   |
| Amp\UvReactor       | [php-uv](https://github.com/chobie/php-uv)            |
| Amp\LibeventReactor | [pecl/libevent](http://pecl.php.net/package/libevent) |


As mentioned, only `UvReactor` and `LibeventReactor` implement the `Amp\SignalReactor` interface
to offer cross-operating system signal handling capabilities. At this time use of the `UvReactor`
is recommended over `LibeventReactor` as the php-uv extension offers more in the way of tangentially
related (but useful) functionality for robust non-blocking applications.

#### Reactor == Task Scheduler

The first thing we need to understand to program effectively using an event loop is this:

> *The event reactor is our task scheduler.*

The reactor controls program flow as long as it runs. Once we tell the reactor to run it will
control program flow until the application errors out, has nothing left to do, or is explicitly
stopped. Consider this very simple example:

```php
<?php // be sure to include the autoload.php file
echo "-before run()-\n";
Amp\run(function() {
    Amp\repeat(function() { echo "tick\n"; }, $msInterval = 1000);
    Amp\once(function() { Amp\stop(); }, $msDelay = 5000);
});
echo "-after stop()-\n";
```

Upon execution of the above example you should see output like this:

```
-before run()-
tick
tick
tick
tick
tick
-after stop()-
```

Hopefully this output demonstrates the concept that what happens inside the event reactor's run loop
is like its own separate program. Your script will not continue past the point of `Reactor::run()`
unless one of the previously mentioned conditions for stoppage is met.

While an application can and often does take place entirely inside the confines of the run loop,
we can also use the reactor to do things like the following example which imposes a short-lived
timeout for interactive console input:

```php
<?php
$number = null;
$stdinWatcher = null;
stream_set_blocking(STDIN, false);

echo "Please input a random number: ";

Amp\run(function() use (&$stdinWatcher, &$number) {
    $stdinWatcher = Amp\onReadable(STDIN, function() use (&$number) {
        $number = fgets(STDIN);
        Amp\stop(); // <-- we got what we came for; exit the loop
    });
    Amp\once(function() {
        Amp\stop(); // <-- you took too long; exit the loop
    }, $msInterval = 5000);
});

if (is_null($number)) {
    echo "You took too long so we chose the number '4' by fair dice roll\n";
} else {
    echo "Your number is: ", (int) $number, "\n";
}

Amp\cancel($stdinWatcher); // <-- clean up after ourselves
stream_set_blocking(STDIN, true);

// Continue doing regular synchronous things here.
```

The details of what's happening in this example are unimportant and involve functionality that will
be covered later. For now, the takeaway should simply be that it's possible tomove in and out of the
event loop like a ninja.


#### The Universal Reactor

In the above example we use the reactor's procedural API to register stream IO and timere watchers.
However, Amp also exposes an object API. Though it almost never makes sense to run multiple event
loop instances in a single-threaded process, instantiating `Reactor` objects in your application
can make things significantly more testable. Note that the function API uses a single static reactor
instance for all operations (universal). Below you'll find the same example from above section
rewritten to use the `Amp\NativeReactor` class .

```php
<?php
$number = null;
$stdinWatcher = null;
stream_set_blocking(STDIN, false);

echo "Please input a random number: ";

$reactor = new Amp\NativeReactor;
$reactor->run(function($reactor) use (&$stdinWatcher, &$number) {
    $stdinWatcher = $reactor->onReadable(STDIN, function() use ($reactor, &$number) {
        $number = fgets(STDIN);
        $reactor->stop();
    });
    $reactor->once(function() {
        $reactor->stop();
    }, $msInterval = 5000);
});

if (is_null($number)) {
    echo "You took too long to respond, so we chose '4' by fair dice roll\n";
} else {
    echo "Your number is: ", (int) $number, "\n";
}

$reactor->cancel($stdinWatcher); // <-- clean up after ourselves
stream_set_blocking(STDIN, true);
```

Always remember: *bugs arising from the existence of multiple reactor instances are exceedingly
difficult to debug.* The reason for this should be relatively clear. It's because running one event
loop will block script execution and prevent others from executing at the same time. This sort of
"loop starvation" results in events that inexplicably fail to trigger. You should endeavor to always
use the same reactor instance in your application when you instantiate and use the object API.
Because the event loop is often a truly global feature of an application the procedural API
functions use a static instance to ensure the same `Reactor` is reused. Be careful about
instantiating reactors manually and mixing in calls to the function API.



## Controlling the Reactor

#### `run()`

The primary way an application interacts with the event reactor is to schedule events for execution
and then simply let the program run. Once `Reactor::run()` is invoked the event loop will run
indefinitely until there are no watchable timer events, IO streams or signals remaining to watch.
Long-running programs generally execute entirely inside the confines of a single `Reactor::run()`
call.


#### `tick()`

The event loop tick is the basic unit of flow control in a non-blocking application. This method
will execute a single iteration of the event loop before returning. `Reactor::tick()` may be used
inside a custom `while` loop to implement "wait" functionality in concurrency primitives such as
futures and promises.


#### `stop()`

The event reactor loop can be stopped at any time while running. When `Reactor::stop()` is invoked
the reactor loop will return control to the userland script at the end of the current iteration
of the event loop. This method may be used to yield control from the reactor even if events or
watchable IO streams are still pending.



## Timer Watchers

Amp exposes several ways to schedule timer watchers. Let's look at some details for each method ...

#### `immediately()`

 - Schedule a callback to execute in the next iteration of the event loop
 - This method guarantees a clean call stack to avoid starvation of other events in the
   current iteration of the loop if called recursively. An "immediately" callback is *always*
   executed in the next tick of the event loop.
 - After an "immediately" timer watcher executes it is automatically garbage collected by
   the reactor so there is no need for applications to manually cancel the associated watcher ID.
 - Like all watchers, "immediately" timers may be disabled and reenabled. If you disable this
   watcher between the time you schedule it and the time that it actually runs the reactor *will
   not* be able to garbage collect it until it executes. Therefore you must manually cancel an
   immediately watcher yourself if it never actually executes to free any associated resources.

#### `once()`

 - Schedule a callback to execute after a delay of *n* milliseconds
 - A "once" watcher is also automatically garbage collected by the reactor after execution and
   applications should not manually cancel it unless they wish to discard the watcher entirely
   prior to execution.
 - A "once" watcher that is disabled has its delay time reset so that the original delay time
   starts again from zero once reenabled.
 - Like "immediately" watchers, a timer scheduled for one-time execution must be manually
   cancelled to free resources if it never runs due to being disabled by the application after
   creation.

#### `repeat()`

 - Schedule a callback to repeatedly execute every *n* millisconds.
 - Unlike one-time watchers, "repeat" timer resources must be explicitly cancelled to free
   the associated resources. Failure to free "repeat" watchers once their purpose is fulfilled
   will result in memory leaks in your application.
 - Like all other watchers, "repeat" timers may be disabled/reenabled at any time.

#### `at()`

 - Schedule a callback to execute at a specific time in the future. Future time may either be
   an integer unix timestamp or any string parsable by PHP's `strtotime()` function.
 - In all other respects "at" watchers are the same as "immediately" and "once" timers.


## Stream IO Watchers

Stream watchers are how we know that data exists to read or that write buffers are empty. These
notifications are how we're able to actually *create* things like http servers and asynchronous
database libraries using the event reactor. As such, stream IO watchers form the backbone of all
non-blocking operations with Amp.

There are two classes of IO watcher:

 - Readability watchers
 - Writability watchers

#### `onReadable()`

Watchers registered via `Reactor::onReadable()` trigger their callbacks in the following situations:

 - When data is available to read on the stream under observation
 - When the stream is at EOF (for sockets, this means the connection is lost)

A common usage pattern for reacting to readable data looks something like this example:

```php
<?php
define('IO_GRANULARITY', 32768);

function isStreamDead($socket) {
    return !is_resource($socket) || @feof($socket);
}

$client->watcherId = Amp\onReadable($client->socket, function() use ($client) {
    $newData = @fread($client->socket, IO_GRANULARITY);
    if ($newData != "") {
        // There was actually data and not an EOF notification. Let's consume it!
        parseIncrementalData($client, $newData);
    } elseif (isStreamDead($client->socket)) {
        // If the read data == "" we need to make sure the stream isn't dead
        closeClientAndClearAnyAssociatedResources($client);
    }
});
```

In the above example we've done a few very simple things:

 - Register a readability watcher for a socket that will trigger our callback when there is
   data available to read.

 - When we read data from the stream in our triggered callback we pass that to a stateful parser
   that does something domain-specific when certain conditions are met.

 - If the `fread()` call indicates that the socket connection is dead we clean up any resources
   we've allocated for the storage of this stream. This process should always include calling
   `Reactor::cancel()` on any reactor watchers we registered in relation to the stream.

#### `onWritable()`

 - Streams are essentially *"always"* writable. The only time they aren't is when their
   respective write buffers are full.

A common usage pattern for reacting to writability involves initializing a writability watcher without
enabling it when a client first connects to a server. Once incomplete writes occur we're then able
to "unpause" the write watcher using `Reactor::enable()` until data is fully sent without having to
create and cancel new watcher resources on the same stream multiple times.


#### `watchStream()`

The `Reactor::watchStream()` functionality exposes both readability and writability watcher
registration in a single function as a convenience for programmers who wish to use the same
API for all IO watchers and specify flags to denote desired behavior.

The `Amp\Reactor` interface exposes the following flags for use with `Reactor::watchStream`:

 - `Reactor::WATCH_READ`
 - `Reactor::WATCH_WRITE`
 - `Reactor::WATCH_NOW`

So if you wished to use the `watchStream()` API to register a readability watcher that was enabled
immediately you would do so like this:

```php
<?php
$flags = Amp\Reactor::WATCH_READ | Reactor::WATCH_NOW;
$readWatcherId = Amp\watchStream($stream, $myCallbackFunction, $flags);
```

> **IMPORTANT:** The main difference between watchStream() and the explicity IO watcher registration
> functions is that watchStream() *WILL NOT* enable watchers by default. To enable a watcher at
> registration time via watchStream() you *must* pass the `WATCH_NOW` flag.


## Pausing, Resuming and Cancelling Watchers

All watchers, regardless of type, can be temporarily disabled and enabled in addition to being
cleared via `Reactor::cancel()`. This allows for advanced capabilities such as disabling the
acceptance of new socket clients in server applications when simultaneity limits are reached. In
general, the performance characteristics of watcher reuse via pause/resume are favorable by
comparison to repeatedly cancelling and re-registering watchers.

#### `disable()`

A simple disable example:

```php
<?php

$reactor = new Amp\NativeReactor;

// Register a watcher we'll disable
$watcherIdToDisable = $reactor->once(function() {
    echo "I'll never execute in one second because: disable()\n";
}, $msDelay = 1000);

// Register a watcher to perform the disable() operation
$reactor->once(function() use ($watcherIdToDisable, $reactor) {
    echo "Disabling WatcherId: ", $watcherIdToDisable, "\n";
    $reactor->disable($watcherIdToDisable);
}, $msDelay = 500);

$reactor->run();
```

After our second watcher callback executes the reactor loop exits because there are no longer any
enabled watchers registered to process.

#### `enable()`

Using `enable()` is just as simple as the `disable()` example we just saw:

```php
<?php

$reactor = new Amp\NativeReactor;

// Register a watcher
$myWatcherId = $reactor->repeat(function() {
    echo "tick\n";
}, $msDelay = 1000);

// Disable the watcher
$reactor->disable($myWatcherId);

// Remember, nothing happens until the reactor runs, so it doesn't matter that we
// previously created and disabled $myWatcherId
$reactor->run(function($reactor) use ($myWatcherId) {
    // Immediately enable the watcher when the reactor starts
    $reactor->enable($myWatcherId);
    // Now that it's enabled we'll see tick output in our console every 1000ms.
});
```

For a slightly more complex use case, let's look at a common scenario where a server might create a
write watcher that is initially disabled but subsequently enabled as necessary:

```php
<?php

class Server {
    private $reactor;
    private $clients = [];
    public function __construct(Amp\Reactor $reactor) {
        $this->reactor = $reactor;
    }

    public function startServer() {
        // ... server bind and accept logic would exist here
        $this->reactor->run();
    }

    private function onNewClient($sock) {
        $socketId = (int) $sock;
        $client = new ClientStruct;
        $client->socket = $sock;
        $readWatcher = $this->reactor->onReadable($sock, function() use ($client) {
            $this->onReadable($client);
        });
        $writeWatcher = $this->reactor->onReadable($sock, function() use ($client) {
            $this->doWrite($client);
        }, $enableNow = false); // <-- let's initialize the watcher as "disabled"

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
            $this->reactor->disable($client->writeWatcher);
        } elseif ($bytesWritten >= 0) {
            $client->writeBuffer = substr($client->writeBuffer, $bytesWritten);
            $this->reactor->enable($client->writeWatcher);
        } elseif ($this->isSocketDead($client->socket)) {
            $this->unloadClient($client);
        }
    }

    // ... other class implementation details here ...
}
```

#### `cancel()`

It's important to *always* cancel persistent watchers once you're finished with them or you'll
create memory leaks in your application. This functionality works in exactly the same way as the
above enable/disable examples:

```php
<?php
Amp\run(function() {
    $myWatcherId = Amp\repeat(function() {
        echo "tick\n";
    }, $msInterval = 1000);

    // Cancel $myWatcherId in five seconds and exit the reactor loop
    Amp\once(function() use ($myWatcherId) {
        Amp\cancel($myWatcherId);
    }, $msDelay = 5000);
});
```

## Process Signal Watchers

The `Amp\SignalReactor` extends the base reactor interface to expose an API for handling process
control signals in your application like any other event. Simply use a compatible event reactor
implementation (`UvReactor` or `LibeventReactor`, preferably the former) and interact with its
`SignalReactor::onSignal()` method. Consider:

```php
<?php
(new Amp\UvReactor)->run(function($reactor) {
    // Let's tick off output once per second so we can see activity.
    $reactor->repeat(function() {
            echo "tick: ", date('c'), "\n";
    }, $msInterval = 1000);

    // What to do when a SIGINT signal is received
    $watcherId = $reactor->onSignal(UV::SIGINT, function() {
        echo "Caught SIGINT! exiting ...\n";
        exit;
    });
});
```

As should be clear from the above example, signal watchers may be enabled, disabled and cancelled
like any other event.



## Addenda

#### Callback Invocation Parameters

All watcher callbacks are invoked using the same standardized parameter order:

| Watcher Type          | Callback Signature                                |
| --------------------- | --------------------------------------------------|
| immediately()         | function(Reactor $reactor, $watcherId)            |
| once()                | function(Reactor $reactor, $watcherId)            |
| repeat()              | function(Reactor $reactor, $watcherId)            |
| at()                  | function(Reactor $reactor, $watcherId)            |
| watchStream()         | function(Reactor $reactor, $watcherId, $stream)   |
| onReadable()          | function(Reactor $reactor, $watcherId, $stream)   |
| onWritable()          | function(Reactor $reactor, $watcherId, $stream)   |
| onSignal()            | function(Reactor $reactor, $watcherId, $signo)    |


#### Watcher Cancellation Safety

It is always safe to cancel a watcher from within its own callback. For example:

```php
<?php
$increment = 0;
Amp\repeat(function($reactor, $watcherId) use (&$increment) {
    echo "tick\n";
    if (++$increment >= 3) {
        $reactor->cancel($watcherId); // <-- cancel myself!
    }
}, $msDelay = 50);
```

#### An Important Note on Writability

Because streams are essentially *"always"* writable you should only enable writability watchers
while you have data to send. If you leave these watchers enabled when your application doesn't have
anything to write the watcher will trigger endlessly until disabled or cancelled. This will max out
your CPU. If you're seeing inexplicably high CPU usage in your application it's a good bet you've
got a writability watcher that you failed to disable or cancel after you were finished with it.


#### Process Signal Number Availability

Using the `SignalReactor` interface is relatively straightforward with the php-uv extension because
it exposes `UV::SIG*` constants for watchable signals. Applications using the `LibeventReactor` to
will need to manually specify the appropriate integer signal numbers when registering signal watchers.


[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"
