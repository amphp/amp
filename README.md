Amp
===

Amp is a non-blocking concurrency framework for PHP applications

**Community**

If you have questions stop by the [amp chat channel](https://gitter.im/amphp) on Gitter.

[![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/amphp?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

**Dependencies**

- PHP 5.5+

Optional PHP extensions may be used to improve performance in production environments. An extension
is also necessary if you need to watch for process signals in your application:

- [php-uv](https://github.com/chobie/php-uv) for libuv backends.
- [*pecl libevent*][libevent] for libevent backends. Windows libevent extension DLLs are
  available [here][win-libevent].

**Installation**

```bash
$ git clone https://github.com/amphp/amp.git
$ cd amp
$ composer.phar install
```



The Guide
----------------------------------------------------------------------------------------------------

### Managing Concurrency

[**Promises**](#promises)

  - [`when()`](#when)
  - [`watch()`](#watch)
  - [`wait()`](#wait)

[**Generators**](#generators)

  - [Manual Generator Resolution](#manual-generator-resolution)

[**Functors**](#functors)

  - [`all()`](#all)
  - [`some()`](#some)
  - [`first()`](#first)
  - [`map()`](#map)
  - [`filter()`](#filter)

[**Promisors**](#promisors)

  - [Future](#future)
  - [PrivateFuture](#privatefuture)

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

[**Addenda**](#addenda)

- [Callback Invocation Parameters](#callback-invocation-parameters)
- [Watcher Cancellation Safety](#watcher-cancellation-safety)
- [An Important Note on Writability Watchers](#an-important-note-on-writability)
- [Process Signal Number Availability](#process-signal-number-availability)


----------------------------------------------------------------------------------------------------

## Managing Concurrency

The biggest difficulty with concurrent processing is humans; we simply don't think asynchronously or
in parallel. Instead, we're really good at doing one thing at a time -- in order -- and the world
around us generally fits this model. So to effectively design for concurrent processing in our code
we have a couple of options:

1. Get smarter (not particularly feasible), or ...
2. Develop abstractions to make concurrent tasks feel synchronous so we can reason about them.

#### Promises

Amp's basic concurrency abstraction is the `Amp\Promise`. These objects should be thought of as
"placeholders" for values or tasks that aren't yet complete. By using placeholders we're able to
reason about the results of concurrent operations as if they were simple variables.

> **IMPORTANT:** `Amp\Promise` does *not* conform to the "Thenables" abstraction common in javascript
> promise implementations. It is this author's opinion that chaining .then() calls is no better at
> avoiding callback hell than other methods. In particular, Amp utilizes generators to accomplish
> the same thing in a more performant way while exposing a more natural error handling mechanism.

In its simplest form the `Amp\Promise` simply aggregates callbacks for dealing with computational
results once they eventually complete. While most code will not interact with this API directly thanks
to the magic of [Generators](#generators), let's take a quick look at the three simple API methods
exposed on `Amp\Promise` implementations:


| Method                | Callback Signature                                |
| --------------------- | --------------------------------------------------|
| void when(callable)   | function(Exception $error = null, $result = null) |
| void watch(callable)  | function($data)                                   |
| mixed wait()          | n/a                                               |


##### when()

`Amp\Promise::when()` accepts an error-first callback. This callback is responsible for reacting to
the eventual result of the computation represented by the promise placeholder. For example:

```php
<?php
$promise = someAsyncFunctionReturningPromise();
$promise->when(function(Exception $error = null, $result = null) {
    if ($error) {
        printf(
            "Something went wrong:\n%s\n",
            $e->getMessage()
        );
    } else {
        printf(
            "Hurray! Our result is:\n%s\n",
            print_r($result, true)
        );
    }
});
```

Those familiar with javascript code generally reflect that the above interface quickly devolves into
["callback hell"](http://callbackhell.com/), and they're correct. We will shortly see how to avoid
this problem in the [Generators](#generators) section.


##### watch()

`Amp\Promise::watch()` affords promise-producers ([Promisors](#promisors)) the ability to broadcast
progress updates while a placeholder value resolves. Whether or not to actually send progress updates
is left to individual libraries, but the functionality is available should applications require it.
A simple example:

```php
<?php
$promise = someAsyncFunctionWithProgressUpdates();
$promise->watch(function($update) {
    printf(
        "Woot, we got an update of some kind:\n%s\n",
        print_r($update, true)
    );
});
```


##### wait()

`Amp\Promise::wait()` allows users to synchronously block script execution until the future value
of a promise is resolved. If the promise resolves successfully this function will return the
resolved value. If a promise fails (i.e. the `$error` parameter in its `when()` callback would be
non-null) the relevant exception is thrown. This means that `wait()` calls on promise instances
should *always* be wrapped in `try\catch` blocks.

```php
<?php
try {
    $result = someAsyncFunction()->wait();
} catch (Exception  $e) {
    echo $e;
}
```

> **IMPORTANT:** Non-blocking applications should *never* use `wait()`. This method exists only to
> simplify the use of async/non-blocking code in synchronous contexts.


#### Generators

The addition of Generators in PHP 5.5 trivializes synchronization and error handling in async contexts.
The Amp event reactor (covered [later](#run)) builds in co-routine support for all reactor callbacks
so we can use the `yield` keyword to make async code feel synchronous. Let's look at a simple example
executing inside the event reactor run loop (covered later):

```php
<?php
Amp\run(function() {
    try {
        // yield control until the promise returned
        // by someAsyncFunction() resolves.
        $a = (yield someAsyncFunction());
    } catch (Exception $e) {
        // if something goes wrong the exception
        // is thrown back to us here.
    }
});
```

As you can see in the above example there is no need for callbacks or `.then()` chaining. Instead,
we're able to use `yield` statements to control program flow even when future computational results
are still pending.

> **IMPORTANT:** Always remember when yielding `Amp\Promise` instances that should the computation
> fail the relevant exception will be thrown back into your generator. Application authors should
> generally always wrap their promise yields in `try/catch` blocks.


| Yieldable        | Description                                                                                                                                                                                                                      |
| -----------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise`    | Any promise instance may be yielded and control will be returned once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. |
| `Generator`      | Any generator instance may also be yielded. The resolution value returned for the yield expression is the final yielded value from generator iteration.                                                                          |


##### Manual Generator Resolution

While Amp will automatically resolve generators resulting from its own callbacks, implementing
co-routines directly in your code is often more useful. Let's look at a very simple server where
we use `Amp\resolve()` as a co-routine to manually resolve a generator function.

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

define('ADDRESS', '127.0.0.1:1337');

function listen($address = null) {
    $address = $address ?: ADDRESS;
    if (!$socket = stream_socket_server($address)) {
        throw new RuntimeException(
            'Failed binding local server socket'
        );
    }

    stream_set_blocking($socket, false);
    Amp\onReadable($socket, 'accept');
}

function accept($reactor, $watcherId, $server) {
    if ($client = @stream_socket_accept($socket)) {
        $generator = onClient($client);
        Amp\resolve($generator); // <-- resolve it!
    }
}

function onClient($client) {
    try {
        $dataToWrite = sprintf('Hello! The current time is %s\n', date('r'));
        yield write($client, $dataToWrite);
        // Disconnect the client once our non-blocking write completes
        @fclose($client);
    } catch (Exception $e) {
        // Write failed. This means the client disconnected
        // so there's nothing left to do here.
    }
}

function write($client, $data) {
    $future = new Amp\Future;

    $bytesWritten = @fwrite($client, $data);

    if ($bytesWritten === strlen($data)) {
        $future->succeed();
    } elseif ($bytesWritten !== false) {
        $data = substr($data, $bytesWritten);
        Amp\onWritable($client, function($r, $w, $client) use ($data, $future) {
            $future->succeed(write($client, $data));
            $r->cancel($w);
        }
    } else {
        $future->fail(new RuntimeException(
            'Failed writing data to client socket'
        ));
    }

    return $future->promise();
}

Amp\run('listen');
```

Note that we can also use an `Amp\Resolver` instance instead of the `Amp\resolve()` function in
object-oriented codebases.


#### Functors

##### all()

The `all()` functor combines an array of promise objects into a single promise that will resolve
when all promises in the group resolve. If any one of the `Amp\Promise` instances fails the
combinator's `Promise` will fail. Otherwise the resulting `Promise` succeeds with an array matching
keys from the input array to their resolved values.

The `all()` combinator is extremely powerful because it allows us to concurrently execute many
asynchronous operations at the same time. Let's look at a simple example using the amp HTTP client
([artax](https://github.com/amphp/artax)) to retrieve multiple HTTP resources concurrently ...

```php
<?php

Amp\run(function() {
    $httpClient = new Amp\Artax\Client;
    $promiseArray = $httpClient->requestMulti([
        'google'    => 'http://www.google.com',
        'news'      => 'http://news.google.com',
        'bing'      => 'http://www.bing.com',
        'yahoo'     => 'https://www.yahoo.com',
    ]);

    try {
        // magic combinator sauce
        $responses = (yield Amp\all($promiseArray));

        foreach ($responses as $key => $response) {
            printf(
                "%s | HTTP/%s %d %s\n",
                $key,
                $response->getProtocol(),
                $response->getStatus(),
                $response->getReason()
            );
        }
    } catch (Exception $e) {
        // If any one of the requests fails the combo
        // promise returned by Amp\all() will fail and
        // be thrown back into our generator here.
        echo $e->getMessage(), "\n";
    }

    Amp\stop();
});
```


##### some()

The `some()` functor is the same as `all()` except that it tolerates individual failures. As long
as at least one promise in the passed array the combined promise will succeed. The successful
resolution value is an array of the form `[$arrayOfErrors, $arrayOfSuccesses]`. The individual keys
in the component arrays are preserved from the promise array passed to the functor for evaluation.

##### first()

Resolves with the first successful result. The resulting Promise will only fail if all
promises in the group fail or if the promise array is empty.

##### map()

Maps eventual promise results using the specified callable.

##### filter()

Filters eventual promise results using the specified callable.

If the functor returns a truthy value the resolved promise result is retained, otherwise it is
discarded. Array keys are retained for any results not filtered out by the functor.

#### Promisors

##### Future

@TODO

##### PrivateFuture

@TODO


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

#### run()

The primary way an application interacts with the event reactor is to schedule events for execution
and then simply let the program run. Once `Reactor::run()` is invoked the event loop will run
indefinitely until there are no watchable timer events, IO streams or signals remaining to watch.
Long-running programs generally execute entirely inside the confines of a single `Reactor::run()`
call.


#### tick()

The event loop tick is the basic unit of flow control in a non-blocking application. This method
will execute a single iteration of the event loop before returning. `Reactor::tick()` may be used
inside a custom `while` loop to implement "wait" functionality in concurrency primitives such as
futures and promises.


#### stop()

The event reactor loop can be stopped at any time while running. When `Reactor::stop()` is invoked
the reactor loop will return control to the userland script at the end of the current iteration
of the event loop. This method may be used to yield control from the reactor even if events or
watchable IO streams are still pending.



## Timer Watchers

Amp exposes several ways to schedule timer watchers. Let's look at some details for each method ...

#### immediately()

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

#### once()

 - Schedule a callback to execute after a delay of *n* milliseconds
 - A "once" watcher is also automatically garbage collected by the reactor after execution and
   applications should not manually cancel it unless they wish to discard the watcher entirely
   prior to execution.
 - A "once" watcher that is disabled has its delay time reset so that the original delay time
   starts again from zero once reenabled.
 - Like "immediately" watchers, a timer scheduled for one-time execution must be manually
   cancelled to free resources if it never runs due to being disabled by the application after
   creation.

#### repeat()

 - Schedule a callback to repeatedly execute every *n* millisconds.
 - Unlike one-time watchers, "repeat" timer resources must be explicitly cancelled to free
   the associated resources. Failure to free "repeat" watchers once their purpose is fulfilled
   will result in memory leaks in your application.
 - Like all other watchers, "repeat" timers may be disabled/reenabled at any time.

#### at()

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

#### onReadable()

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

#### onWritable()

 - Streams are essentially *"always"* writable. The only time they aren't is when their
   respective write buffers are full.

A common usage pattern for reacting to writability involves initializing a writability watcher without
enabling it when a client first connects to a server. Once incomplete writes occur we're then able
to "unpause" the write watcher using `Reactor::enable()` until data is fully sent without having to
create and cancel new watcher resources on the same stream multiple times.


#### watchStream()

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

#### disable()

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

#### enable()

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

#### cancel()

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
