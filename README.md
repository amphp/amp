Alert
=====

Alert provides event reactors for powering event-driven, non-blocking PHP applications.

**Features**

Alert adds the following functionality previously absent from the PHP non-blocking space:

- Pause/resume for individual event/signal/IO observers
- Multiple watchers for individual streams
- Cross-OS process signal handling (yes, even in Windows)

**Dependencies**

- PHP 5.4+

Optional PHP extensions for great performance justice:

- (preferred) [php-uv](https://github.com/chobie/php-uv) for libuv backends.
- [*pecl libevent*][libevent] for libevent backends. Windows libevent extension DLLs are
  available [here][win-libevent]. php-uv is preferred, but libevent is better than nothing.

**Installation**

Via composer:

```bash
$ php composer.phar require rdlowrey/alert:~0.11.x
```


----------------------------------------------------------------------------------------------------
The Guide
----------------------------------------------------------------------------------------------------

**1. Event Reactor Concepts**

 - a. Reactor Implementations
 - b. Reactor == Task Scheduler
 - c. The Universal Reactor

**2. Controlling the Reactor**

 - a. `run()`
 - b. `tick()`
 - c. `stop()`

**2. Timer Watchers**

 - a. `immediately()`
 - b. `once()`
 - c. `repeat()`
 - d. `at()`

**3. Stream IO Watchers**

 - a. `onReadable()`
 - b. `onWritable()`
 - c. `watchStream()`
 - d. A Note on Writability
 - e. A Note on IO Performance

**4. Process Signal Watchers**

 - @TODO

**5. Pausing, Resuming and Cancelling Watchers**

 - a. `disable()`
 - b. `enable()`
 - c. `cancel()`

**6. Common Patterns**

 - @TODO

----------------------------------------------------------------------------------------------------


## 1. Event Reactor Concepts

##### a. Reactor Implementations

It may surprise people to learn that the PHP standard library already has everything we need to
write event-driven and non-blocking applications. We only reach the limits of native PHP's
functionality in this area when we ask it to poll several hundred streams for read/write capability
at the same time. Even in this case, though, the fault is not with PHP but the underlying system
`select()` call which is linear in its performance degradation as load increases.

For performance that scales out to high volume we require more advanced capabilities currently
found only in extensions. If you wish to, for example, service 10,000 simultaneous clients in an
Alert-backed socket server you would definitely need to use one of the reactors based on a PHP
extension. However, if you're using Alert in a strictly local program for non-blocking concurrency
or you don't need to handle more than ~100 or so simultaneous clients in a server application the
native PHP functionality is perfectly adequate.

Alert currently exposes three separate implementations for its standard `Reactor` interface. Each
behaves exactly the same way from an external API perspective. The main differences have to do
with underlying performance characteristics. The one capability that the extension-based reactors
*do* offer that's unavailable with the native implementation is the ability to watch for process
control signals. The current implementations are listed here:

 - `Alert\NativeReactor` (native php)
 - `Alert\UvReactor` (libuv via the php-uv extension)
 - `Alert\LibeventReactor` (libevent via pecl/libevent)

As mentioned, only `UvReactor` and `LibeventReactor` implement the `Alert\SignalReactor` interface
to offer cross-operating system signal handling capabilities. At this time use of the `UvReactor`
is recommended over `LibeventReactor` as the php-uv extension offers more in the way of tangentially
related (but useful) functionality for robust non-blocking applications than libevent.

##### b. Reactor == Task Scheduler

The first thing we need to understand to program effectively using an event loop is this:

> *The event reactor is our task scheduler.*

The reactor controls program flow as long as it runs. Once we tell the reactor to run it will
control program flow until the application errors out, has nothing left to do, or is explicitly
stopped. Consider this very simple example:

```php
<?php // be sure to include the autoload.php file
echo "-before run()-\n";
Alert\run(function() {
    Alert\repeat(function() { echo "tick\n"; }, $msInterval = 1000);
    Alert\once(function() { Alert\stop(); }, $msDelay = 5000);
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

This simple example should be enough to demonstrate the concept that what happens inside the event
reactor's run loop is like its own separate program. Your script will not continue past the point
where `Reactor::run()` unless one of the previously mentioned conditions for stoppage is met.

While an application can and often does take place entirely inside the confines of the run loop,
we can also use the reactor to do things like the following example which imposes a short-lived
timeout for interactive console input:

```php
<?php
$number = null;
$stdinWatcher = null;
stream_set_blocking(STDIN, false);

echo "Please input a random number: ";

Alert\run(function() use (&$stdinWatcher, &$number) {
    $stdinWatcher = Alert\onReadable(STDIN, function() use (&$number) {
        $number = fgets(STDIN);
        Alert\stop(); // <-- we got what we came for; exit the loop
    });
    Alert\once(function() {
        Alert\stop(); // <-- you took too long; exit the loop
    }, $msInterval = 5000);
});

if (is_null($number)) {
    echo "You took too long to respond, so we chose the number, '4' by fair dice roll\n";
} else {
    echo "Your number is: ", (int) $number, "\n";
}

Alert\cancel($stdinWatcher); // <-- clean up after ourselves
stream_set_blocking(STDIN, true);
```

The details of what's happening in this example are unimportant and involve functionality that will
be covered later. For now, the takeaway should simply be that you can move in and out of the event
loop like a ninja if you wish.


##### c. The Universal Reactor

In the above example we use the reactor's procedural API to register stream IO and timere watchers.
However, Alert also exposes an object API. Though it almost never makes sense to run multiple event
loop instances in a single-threaded process, instantiating `Reactor` objects in your application
can make things significantly more testable. Note that the function API uses a single static reactor
instance for all operations (universal). Below you'll find the same example from above section
rewritten to use the `Alert\NativeReactor` class .

```php
<?php
$number = null;
$stdinWatcher = null;
stream_set_blocking(STDIN, false);

echo "Please input a random number: ";

$reactor = new Alert\NativeReactor;
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
difficult to debug.* The reason for this should be relatively clear: running one event loop will
block script execution and prevent others from executing at the same time. This sort of "loop
starvation" results in events that inexplicably fail to trigger. You should endeavor to always use
the same reactor instance in your application when you instantiate and use the object API. Because
the event loop is often a truly global feature of an application the procedural API functions use
a static instance to ensure the same `Reactor` is reused. Be careful about instantiating reactors
manually and mixing in calls to the function API.



## 2. Controlling the Reactor

@TODO

##### a. `run()`

@TODO


##### b. `tick()`

@TODO


##### c. `stop()`

@TODO



## 3. Timer Watchers

Alert exposes several ways to schedule future events:

 - `Alert\Reactor::immediately()` | `Alert\immediately()`
 - `Alert\Reactor::once()` | `Alert\once()`
 - `Alert\Reactor::repeat()` | `Alert\repeat()`
 - `Alert\Reactor::at()` | `Alert\at()`

Let's look at the details for these messages ...

##### a. `immediately()`

 - Schedule a callback to execute in the next iteration of the event loop
 - This method guarantees a "clean" call stack to avoid starvation of other events in the
   current iteration of the loop if called continuously
 - After an "immediately" timer watcher executes it is automatically garbage collected by
   the reactor so there is no need for applications to manually cancel the associated watcher ID.
 - Like all watchers, "immediately" timers may be disabled and reenabled. If you disable this
   watcher between when you first schedule it and when it runs the reactor *will not* be able
   to garbage collect it until it executes. Therefore you must manually cancel an immediately
   watcher yourself if it never actually executes to free associate resources.

##### b. `once()`

 - Schedule a callback to execute after a delay of *n* milliseconds
 - A "once" watcher is also automatically garbage collected by the reactor after execution and
   applications should not manually cancel it unless they wish to discard the watcher entirely
   prior to execution.
 - A "once" watcher that is disabled has its delay time reset so that the original delay time
   starts again from zero once reenabled.
 - Like "immediately" watchers, a timer scheduled for one-time execution must be manually
   cancelled to free resources if it never runs due to being disabled by the application after
   creation.

##### c. `repeat()`

 - Schedule a callback to repeatedly execute every *n* millisconds.
 - Unlike one-time watchers, "repeat" timer resources must be explicitly cancelled to free
   the associated resources. Failure to free "repeat" watchers once their purpose is fulfilled
   will result in memory leaks in your application.
 - Like all other watchers, "repeat" timers may be disabled/reenabled at any time.

##### d. `at()`

 - Schedule a callback to execute at a specific time in the future. Future time may either be
   an integer unix timestamp or any string parsable by PHP's `strtotime()` function.
 - In all other respects "at" watchers are the same as "immediately" and "once" timers.


## 4. Stream IO Watchers

Stream watchers are how we know that data exists to read or that write buffers are empty. These
notifications are how we're able to actually *create* things like http servers and asynchronous
database libraries using the event reactor. As such, stream IO watchers form the backbone of all
non-blocking operations with Alert.

There are two classes of IO watcher:

 - Readability watchers
 - Writability watchers

##### a. An Important Note on Writability

Before continuing we should note one very important point about writability watchers:

> **IMPORTANT:** Because streams are essentially *"always"* writable you should only enable
> writability watchers while you have data to send. If you leave these watchers enabled when your
> application doesn't have anything to write the watcher will trigger endlessly until disabled
> or cancelled. This will max out your CPU. If you're seeing inexplicably high CPU usage in your
> application it's a good bet you've got a writability watcher that you failed to disable or
> cancel after you were finished with it.

Now that's out of the way let's look at how Alert reactors expose IO watcher functionality ...

##### b. `onReadable()`

Watchers registered via `Reactor::onReadable()` trigger their callbacks in the following situations:

 - Triggered when data is available to read on the watched stream
 - Also triggered if the stream is at EOF (for sockets, this means the connection is lost)

A common usage pattern for reacting to readable data looks something like this example:

```php
<?php
define('IO_GRANULARITY', 32768);

function isStreamDead($socket) {
    return !is_resource($socket) || @feof($socket);
}

$client->watcherId = Alert\onReadable($client->socket, function() use ($client) {
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

 1. Register a readability watcher for a socket that will trigger our callback when there is
    data available to read.
 2. When we read data from the stream in our triggered callback we pass that to a stateful parser
    that does something domain-specific when certain conditions are met.
 3. If the `fread()` call indicates that the socket connection is dead we clean up any resources
    we've allocated for the storage of this stream. This process should always include calling
    `Reactor::cancel()` on any reactor watchers we registered in relation to the stream.

##### c. `onWritable()`

 - Streams are essentially *"always"* writable. The only time they aren't is when their
   respective write buffers are full.

A common usage pattern for reacting to writability looks something like this example:

```php
<?php
// @TODO Add example code
```

##### d. `watchStream()`

The `Reactor::watchStream()` functionality exposes both readability and writability watcher
registration in a single function as a convenience for programmers who wish to use the same
API for all IO watchers and specify flags to denote desired behavior.

The `Alert\Reactor` interface exposes the following flags for use with `Reactor::watchStream`:

 - `Reactor::WATCH_READ`
 - `Reactor::WATCH_WRITE`
 - `Reactor::WATCH_NOW`

So if you wished to use the `watchStream()` API to register a readability watcher that was enabled
immediately you would do so like this:

```php
<?php
$flags = Alert\Reactor::WATCH_READ | Reactor::WATCH_NOW;
$readWatcherId = Alert\watchStream($stream, $myCallbackFunction, $flags);
```

> **IMPORTANT:** The main difference between watchStream() and the explicity IO watcher registration
> functions is that watchStream() *WILL NOT* enable watchers by default. To enable a watcher at
> registration time via watchStream() you *must* pass the `WATCH_NOW` flag.


##### e. A Note on IO Performance

@TODO Talk about why we don't use event emitters, buffers and thenables for low-level operations ...


## 5. Process Signal Watchers

@TODO


## 6. Pausing, Resuming and Cancelling Watchers

@TODO

##### a. `disable()`

@TODO

##### b. `enable()`

@TODO

##### c. `cancel()`

@TODO


[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"
