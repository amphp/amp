Alert
=====

Alert provides native, libevent and libuv event reactors for powering event-driven PHP applications
and servers.

#### Features

Alert adds the following functionality previously absent from the PHP non-blocking space:

- Pause/resume for individual event/signal/IO observers
- Multiple watchers for individual streams
- Cross-OS process signal handling (yes, even in Windows)

#### Dependencies

* PHP 5.4+
* (optional) [php-uv](https://github.com/chobie/php-uv) for libuv backends.
* (optional) [*PECL libevent*][libevent] for libevent backends. Windows libevent extension DLLs are
  available [here][win-libevent]

#### Installation

```bash
$ php composer.phar require rdlowrey/alert:~0.10.x
```

The Guide
---------

**1. Event Reactor Concepts**

 - a. Reactor == Task Scheduler
 - b. The Universal Reactor
 - @TODO Discuss the different reactor implementations

**2. Timer Watchers**

 - a. `immediately()`
 - b. `once()`
 - c. `repeat()`
 - d. `at()`

**3. Stream IO Watchers**

 - a. An Important Note on Writability
 - b. `onReadable()`
 - c. `onWritable()`
 - d. `watchStream()`
 - e. A Note on IO Performance

**4. Process Signal Watchers**

 - @TODO

**5. Pausing, Resuming and Cancelling Watchers**

 - a. `disable()`
 - b. `enable()`
 - c. `cancel()`


----------------------------------------------------------------------------------------------------


### 1. Event Reactor Concepts


###### a. Reactor == Task Scheduler

The event reactor is our task scheduler. It controls program flow as long as it runs. In this
example we run a program that counts down for ten seconds before exiting. Meanwhile, any input
sent from your console's STDIN stream is echoed back out to demonstrate listening for IO
availability on a stream. Notice how we explicitly invoke `Reactor::stop()` to end the event
loop and return flow control back to PHP. The event reactor will automatically return control
to the PHP script if it has no more timers, streams or signals to watch, and in such cases the
`Reactor::stop()` call is unnecessary. However, if *any* watchers are still registered (e.g. the
STDIN stream watcher in this example) the reactor will continue to run until remaining watchers
are cancelled via `Reactor::cancel($watcherId)` or the reactor is manually stopped.

```php
<?php
define('RUN_TIME', 10);
(new ReactorFactory)->select()->run(function(Reactor $reactor) {
    // Set the STDIN stream to "non-blocking" mode
    stream_set_blocking(STDIN, false);

    // Echo back the line each time there is readable data on STDIN
    $reactor->onReadable(STDIN, function() {
        if ($line = fgets(STDIN)) {
            echo "INPUT> ", $line, "\n";
        }
    });

    // Countdown RUN_TIME seconds then end the event loop
    $secondsRemaining = RUN_TIME;
    $reactor->repeat(function() use (&$secondsRemaining) {
        if (--$secondsRemaining > 0) {
            echo "$secondsRemaining seconds to shutdown\n";
        } else {
            $reactor->stop();
        }
    }, $msInterval = 1000);
});
```


###### b. The Universal Reactor

In the above example we use the reactor's object API to register watchers. However, Alert also
exposes a set of global functions to do the same things because it almost never makes sense to
run multiple event loops in a single-threaded process. Unless your application needs multiple
event loops (SPOILER ALERT: it almost certainly doesn't) you may prefer to use the global function
API to interact with the event reactor. The function API uses a single static event reactor instance
for all operations (universal). Below you'll find the same example from above using the function
API. Always remember: *bugs arising from the existence of multiple reactor instances are very
difficult to debug!* You should endeavor to always use the same reactor in your application and
the function API *may* help you with this:

```php
<?php
Alert\run(function() {
    // Set the STDIN stream to "non-blocking" mode
    stream_set_blocking(STDIN, false);

    // Echo back the line each time there is readable data on STDIN
    Alert\onReadable(STDIN, function() {
        if ($line = fgets(STDIN)) {
            echo "INPUT> ", $line, "\n";
        }
    });

    // Countdown RUN_TIME seconds then end the event loop
    $secondsRemaining = RUN_TIME;
    Alert\repeat(function() use (&$secondsRemaining) {
        if (--$secondsRemaining > 0) {
            echo "$secondsRemaining seconds to shutdown\n";
        } else {
            Alert\stop(); // <-- explicitly stop the loop
        }
    }, $msInterval = 1000);
});
```

### 2. Timer Watchers

Alert exposes several ways to schedule future events:

    * `Alert\Reactor::immediately()` | `Alert\immediately()`
    * `Alert\Reactor::once()` | `Alert\once()`
    * `Alert\Reactor::repeat()` | `Alert\repeat()`
    * `Alert\Reactor::at()` | `Alert\at()`

Each method name accurately describes its purpose. However, let's look at some details ...

###### a. `immediately()`

 - Schedule a callback to execute in the next iteration of the event loop
 - This method guarantees a "clean" call stack to avoid starvation of other events in the
   current iteration of the loop if called continuously
 - After an "immediately" timer watcher executes it is automatically garbage collected by
   the reactor so there is no need for applications to manually cancel the associated watcher ID.
 - Like all watchers, "immediately" timers may be disabled and reenabled. If you disable this
   watcher between when you first schedule it and when it runs the reactor *will not* be able
   to garbage collect it until it executes. Therefore you must manually cancel an immediately
   watcher yourself if it never actually executes to free associate resources.

###### b. `once()`

 - Schedule a callback to execute after a delay of *n* milliseconds
 - A "once" watcher is also automatically garbage collected by the reactor after execution and
   applications should not manually cancel it unless they wish to discard the watcher entirely
   prior to execution.
 - A "once" watcher that is disabled has its delay time reset so that the original delay time
   starts again from zero once reenabled.
 - Like "immediately" watchers, a timer scheduled for one-time execution must be manually
   cancelled to free resources if it never runs due to being disabled by the application after
   creation.

###### c. `repeat()`

 - Schedule a callback to repeatedly execute every *n* millisconds.
 - Unlike one-time watchers, "repeat" timer resources must be explicitly cancelled to free
   the associated resources. Failure to free "repeat" watchers once their purpose is fulfilled
   will result in memory leaks in your application.
 - Like all other watchers, "repeat" timers may be disabled/reenabled at any time.

###### d. `at()`

 - Schedule a callback to execute at a specific time in the future. Future time may either be
   an integer unix timestamp or any string parsable by PHP's `strtotime()` function.
 - In all other respects "at" watchers are the same as "immediately" and "once" timers.


### 3. Stream IO Watchers

Stream watchers are how we know that data exists to read or that write buffers are empty. These
notifications are how we're able to actually *create* things like http servers and asynchronous
database libraries using the event reactor. As such, stream IO watchers form the backbone of all
non-blocking operations with Alert.

There are two classes of IO watcher:

 - Readability watchers
 - Writability watchers

###### a. An Important Note on Writability

Before continuing we should note one very important point about writability watchers:

> **IMPORTANT:** Because streams are essentially *"always"* writable you should only enable
> writability watchers while you have data to send. If you leave these watchers enabled when your
> application doesn't have anything to write the watcher will trigger endlessly until disabled
> or cancelled. This will max out your CPU. If you're seeing inexplicably high CPU usage in your
> application it's a good bet you've got a writability watcher that you failed to disable or
> cancel after you were finished with it.

Now that's out of the way let's look at how Alert reactors expose IO watcher functionality ...

###### b. `onReadable()`

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

###### c. `onWritable()`

 - Streams are essentially *"always"* writable. The only time they aren't is when their
   respective write buffers are full.

A common usage pattern for reacting to writability looks something like this example:

```php
<?php
// @TODO Add example code
```

###### d. `watchStream()`

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


###### e. A Note on IO Performance

@TODO Talk about why we don't use event emitters, buffers and thenables for low-level operations ...


### 4. Process Signal Watchers

@TODO


### 5. Pausing, Resuming and Cancelling Watchers

@TODO

###### a. `disable()`

@TODO

###### b. `enable()`

@TODO

###### c. `cancel()`

@TODO


[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"
