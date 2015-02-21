The Amp Guide
=============

Amp is a non-blocking concurrency framework for PHP applications

**About This Document**

If you're reading The Amp Guide as a github markdown file you should click the link below to view the styled HTML version. The github markdown display is really unflattering and misses out on features like the Table of Contents. Please don't subject your eyes to this monstrosity; click here instead:

[**Show me the Amp Guide the way nature intended!**](http://amphp.github.io/amp/)

**Dependencies**

- PHP 5.5+

Optional PHP extensions may be used to improve performance in production environments and react to process control signals:

- [php-uv](https://github.com/chobie/php-uv) extension for libuv backends
- [pecl/libevent](http://pecl.php.net/package/libevent) for libevent backends ([download Windows .dll](http://windows.php.net/downloads/pecl/releases/libevent/0.0.5/))

**Installation**

```bash
$ git clone https://github.com/amphp/amp.git
$ cd amp
$ composer.phar install
```

**Community**

If you have questions stop by the [amp chat channel](https://gitter.im/amphp/amp) on Gitter.


# Table of Contents

[TOC]

---

# Event Reactor Concepts

## Reactor Implementations

It may surprise people to learn that the PHP standard library already has everything we need to write event-driven and non-blocking applications. We only reach the limits of native PHP's functionality in this area when we ask it to poll several hundred streams for read/write capability at the same time. Even in this case, though, the fault is not with PHP but the underlying system `select()` call which is linear in its performance  degradation as load increases.

For performance that scales out to high volume we require more advanced capabilities currently found only in extensions. If you wish to, for example, service 10,000 simultaneous clients in an Amp-backed socket server you would definitely need to use one of the reactors based on a PHP extension. However, if you're using Amp in a strictly local program for non-blocking concurrency or you don't need to handle more than ~100 or so simultaneous clients in a server application the native PHP functionality is perfectly adequate.

Amp currently exposes three separate implementations for its standard `Reactor` interface. Each behaves exactly the same way from an external API perspective. The main differences have to do with underlying performance characteristics. The one capability that the extension-based reactors *do* offer that's unavailable with the native implementation is the ability to watch for process control signals. The current implementations are listed here:


| Class                 | Extension                                             |
| --------------------- | ----------------------------------------------------- |
| Amp\NativeReactor     | n/a                                                   |
| Amp\UvReactor         | [php-uv](https://github.com/chobie/php-uv)            |
| Amp\LibeventReactor   | [pecl/libevent](http://pecl.php.net/package/libevent) |


As mentioned, only `UvReactor` and `LibeventReactor` implement the `Amp\SignalReactor` interface to offer cross-operating system signal handling capabilities. At this time use of the `UvReactor` is recommended over `LibeventReactor` as the php-uv extension offers more in the way of tangentially related (but useful) functionality for robust non-blocking applications.

## Reactor == Task Scheduler

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

Hopefully this output demonstrates the concept that what happens inside the event reactor's run loop is like its own separate program. Your script will not continue past the point of `Reactor::run()` unless one of the previously mentioned conditions for stoppage is met.

While an application can and often does take place entirely inside the confines of the run loop, we can also use the reactor to do things like the following example which imposes a short-lived timeout for interactive console input:

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

The details of what's happening in this example are unimportant and involve functionality that will be covered later. For now, the takeaway should simply be that it's possible to move in and out of the event loop like a ninja.


## The Universal Reactor

In the above example we use the reactor's procedural API to register stream IO and timer watchers. However, Amp also exposes an object API. Though it almost never makes sense to run multiple event loop instances in a single-threaded process, instantiating `Reactor` objects in your application can make things significantly more testable. Note that the function API uses a single static reactor instance for all operations (universal). Below you'll find the same example from above section rewritten to use the `Amp\NativeReactor` class .

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
    $reactor->once(function(Amp\Reactor $reactor) {
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

Always remember: *bugs arising from the existence of multiple reactor instances are exceedingly difficult to debug.* The reason for this should be relatively clear. It's because running one event
loop will block script execution and prevent others from executing at the same time. This sort of "loop starvation" results in events that inexplicably fail to trigger. You should endeavor to always use the same reactor instance in your application when you instantiate and use the object API. Because the event loop is often a truly global feature of an application the procedural API functions use a static instance to ensure the same `Reactor` is reused. Be careful about instantiating reactors manually and mixing in calls to the function API.



# Controlling the Reactor

## run()

The primary way an application interacts with the event reactor is to schedule events for execution
and then simply let the program run. Once `Reactor::run()` is invoked the event loop will run
indefinitely until there are no watchable timer events, IO streams or signals remaining to watch.
Long-running programs generally execute entirely inside the confines of a single `Reactor::run()`
call.


## tick()

The event loop tick is the basic unit of flow control in a non-blocking application. This method
will execute a single iteration of the event loop before returning. `Reactor::tick()` may be used
inside a custom `while` loop to implement "wait" functionality in concurrency primitives such as
futures and promises.


## stop()

The event reactor loop can be stopped at any time while running. When `Reactor::stop()` is invoked
the reactor loop will return control to the userland script at the end of the current iteration
of the event loop. This method may be used to yield control from the reactor even if events or
watchable IO streams are still pending.



## Timer Watchers

Amp exposes several ways to schedule timer watchers. Let's look at some details for each method ...

### immediately()

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

### once()

 - Schedule a callback to execute after a delay of *n* milliseconds
 - A "once" watcher is also automatically garbage collected by the reactor after execution and
   applications should not manually cancel it unless they wish to discard the watcher entirely
   prior to execution.
 - A "once" watcher that is disabled has its delay time reset so that the original delay time
   starts again from zero once reenabled.
 - Like "immediately" watchers, a timer scheduled for one-time execution must be manually
   cancelled to free resources if it never runs due to being disabled by the application after
   creation.

### repeat()

 - Schedule a callback to repeatedly execute every *n* millisconds.
 - Unlike one-time watchers, "repeat" timer resources must be explicitly cancelled to free
   the associated resources. Failure to free "repeat" watchers once their purpose is fulfilled
   will result in memory leaks in your application.
 - Like all other watchers, "repeat" timers may be disabled/reenabled at any time.

### at()

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

### onReadable()

Watchers registered via `Reactor::onReadable()` trigger their callbacks in the following situations:

 - When data is available to read on the stream under observation
 - When the stream is at EOF (for sockets, this means the connection is lost)

A common usage pattern for reacting to readable data looks something like this example:

```php
<?php
define("IO_GRANULARITY", 32768);

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

### onWritable()

 - Streams are essentially *"always"* writable. The only time they aren't is when their
   respective write buffers are full.

A common usage pattern for reacting to writability involves initializing a writability watcher without enabling it when a client first connects to a server. Once incomplete writes occur we're then able to "unpause" the write watcher using `Reactor::enable()` until data is fully sent without having to create and cancel new watcher resources on the same stream multiple times.


## Pausing, Resuming and Cancelling Watchers

All watchers, regardless of type, can be temporarily disabled and enabled in addition to being
cleared via `Reactor::cancel()`. This allows for advanced capabilities such as disabling the acceptance of new socket clients in server applications when simultaneity limits are reached. In general, the performance characteristics of watcher reuse via pause/resume are favorable by comparison to repeatedly cancelling and re-registering watchers.

### disable()

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

After our second watcher callback executes the reactor loop exits because there are no longer any enabled watchers registered to process.

### enable()

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

For a slightly more complex use case, let's look at a common scenario where a server might create a write watcher that is initially disabled but subsequently enabled as necessary:

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

### cancel()

It's important to *always* cancel persistent watchers once you're finished with them or you'll create memory leaks in your application. This functionality works in exactly the same way as  the above enable/disable examples:

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

As should be clear from the above example, signal watchers may be enabled, disabled and cancelled like any other event.



## Reactor Addenda

### Callback Invocation Parameters

All watcher callbacks are invoked using the same standardized parameter order:

| Watcher Type          | Callback Signature                                |
| --------------------- | --------------------------------------------------|
| immediately()         | `function(Reactor $reactor, $watcherId)`          |
| once()                | `function(Reactor $reactor, $watcherId)`          |
| repeat()              | `function(Reactor $reactor, $watcherId)`          |
| at()                  | `function(Reactor $reactor, $watcherId)`          |
| watchStream()         | `function(Reactor $reactor, $watcherId, $stream)` |
| onReadable()          | `function(Reactor $reactor, $watcherId, $stream)` |
| onWritable()          | `function(Reactor $reactor, $watcherId, $stream)` |
| onSignal()            | `function(Reactor $reactor, $watcherId, $signo)`  |


### Watcher Cancellation Safety

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

### An Important Note on Writability

Because streams are essentially *"always"* writable you should only enable writability watchers
while you have data to send. If you leave these watchers enabled when your application doesn't have
anything to write the watcher will trigger endlessly until disabled or cancelled. This will max out
your CPU. If you're seeing inexplicably high CPU usage in your application it's a good bet you've
got a writability watcher that you failed to disable or cancel after you were finished with it.


### Process Signal Number Availability

Using the `SignalReactor` interface is relatively straightforward with the php-uv extension because
it exposes `UV::SIG*` constants for watchable signals. Applications using the `LibeventReactor` to
will need to manually specify the appropriate integer signal numbers when registering signal watchers.


[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"


# Managing Concurrency

The weak link when managing concurrency is humans; we simply don't think asynchronously or
in parallel. Instead, we're really good at doing one thing at a time, in order, and the world
around us generally fits this model. So to effectively design for concurrent processing in our code
we have a couple of options:

1. Get smarter (not particularly feasible);
2. Abstract concurrent task execution to make it feel synchronous.

## Promises

The basic unit of concurrency in an Amp application is the `Amp\Promise`. These objects should be thought of as "placeholders" for values or tasks that aren't yet complete. By using placeholders we're able to reason about the results of concurrent operations as if they were already complete variables.

> **NOTE**
> 
> Amp promises do *not* conform to the "Thenables" abstraction common in javascript promise implementations. It is this author's opinion that chaining .then() calls is a clunky way to avoid callback hell with awkward error handling results. Instead, Amp utilizes PHP 5.5's generator functionality to accomplish the same thing in a more performant way with superior error handling capabilities.

### The Promise API

```php
interface Promise {
    public function when(callable $func);
    public function watch(callable $func);
}
```

In its simplest form the `Amp\Promise` aggregates callbacks for dealing with computational results once they eventually resolve. While most code will not interact with this API directly thanks to the magic of [Generators](#generators), let's take a quick look at the two simple API methods exposed on `Amp\Promise` implementations:


| Method                | Callback Signature                                |
| --------------------- | --------------------------------------------------|
| void when(callable)   | `function(Exception $error = null, $result = null)` |
| void watch(callable)  | `function($data)`                                   |


### when()

`Amp\Promise::when()` accepts an error-first callback. This callback is responsible for reacting to the eventual result of the computation represented by the promise placeholder. For example:

```php
<?php
$promise = someAsyncFunctionThatReturnsAPromise();
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

Those familiar with javascript code generally reflect that the above interface quickly devolves into ["callback hell"](http://callbackhell.com/), and they're correct. We will shortly see how to avoid this problem in the [Generators](#generators) section.


### watch()

`Amp\Promise::watch()` affords promise-producers ([Promisors](#promisors)) the ability to broadcast progress updates while a placeholder value resolves. Whether or not to actually send progress updates is left to individual libraries, but the functionality is available should applications require it. A simple example:

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

## Promisors

`Amp\Promisor` is the abstraction responsible for resolving future values once they become available. A library that resolves values asynchronously creates an `Amp\Promisor` and uses it to return an `Amp\Promise` to API consumers. Once the async library determines that the value is ready it resolves the promise held by the API consumer using methods on the linked promisor.

### The Promisor API

```php
interface Promisor {
    public function promise();
    public function update($progress);
    public function succeed($result = null);
    public function fail(\Exception $error);
}
```

Amp provides two base implementations for async value promisors: `Amp\Future` and `Amp\PrivateFuture`.

### Future Promisor

The standard `Amp\Future` is the more performant option of the two default `Amp\Promisor` implementations. It acts both as promisor and promise to minimize the number of new object/closure instantiations needed to resolve an async value. The drawback to this approach is that any code with a reference to the `Future` promisor can resolve the associated Promise.

### PrivateFuture Promisor

The `Amp\PrivateFuture` is more concerned with code safety than performance. It *does not* act as its own promise. Only code with a reference to a `PrivateFuture` instance can resolve its associated promise.

### Promisor Example

Here's a simple example of an async value producer `asyncMultiply()` creating a promisor and returning the associated promise to its API consumer. Note that the code below would work exactly the same had we used a `PrivateFuture` as our promisor instead of the `Future` employed below.

```php
<?php // Example async producer using promisor

function asyncMultiply($x, $y) {
	// Create a new promisor
	$promisor = new Amp\Future;
	
	// Resolve the async result one second from now
	Amp\once(function() use ($promisor, $x, $y) {
		$promisor->succeed($x * $y);
	}, $msDelay = 1000);
	
	return $promisor->promise();
}

$promise = asyncMultiply(6, 7);
$result = Amp\wait($promise);
var_dump($result); // int(42)
```

## Combinators

### all()

The `all()` functor combines an array of promise objects into a single promise that will resolve
when all promises in the group resolve. If any one of the `Amp\Promise` instances fails the
combinator's `Promise` will fail. Otherwise the resulting `Promise` succeeds with an array matching
keys from the input array to their resolved values.

The `all()` combinator is extremely powerful because it allows us to concurrently execute many
asynchronous operations at the same time. Let's look at a simple example using the amp HTTP client
([artax](https://github.com/amphp/artax)) to retrieve multiple HTTP resources concurrently ...

```php
<?php
use function Amp\run;
use function Amp\all;

run(function() {
    $httpClient = new Amp\Artax\Client;
    $promiseArray = $httpClient->requestMulti([
        "google"    => "http://www.google.com",
        "news"      => "http://news.google.com",
        "bing"      => "http://www.bing.com",
        "yahoo"     => "https://www.yahoo.com",
    ]);

    try {
        // magic combinator sauce to flatten the promise
        // array into a single promise
        $responses = (yield all($promiseArray));

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


### some()

The `some()` functor is the same as `all()` except that it tolerates individual failures. As long
as at least one promise in the passed array the combined promise will succeed. The successful
resolution value is an array of the form `[$arrayOfErrors, $arrayOfSuccesses]`. The individual keys
in the component arrays are preserved from the promise array passed to the functor for evaluation.

### first()

Resolves with the first successful result. The resulting Promise will only fail if all
promises in the group fail or if the promise array is empty.

### map()

Maps eventual promise results using the specified callable.

### filter()

Filters eventual promise results using the specified callable.

If the functor returns a truthy value the resolved promise result is retained, otherwise it is
discarded. Array keys are retained for any results not filtered out by the functor.


## Generators

The addition of Generators in PHP 5.5 trivializes synchronization and error handling in async contexts. The Amp event reactor builds in co-routine support for all reactor callbacks so we can use the `yield` keyword to make async code feel synchronous. Let's look at a simple example executing inside the event reactor run loop:

```php
<?php

use function Amp\run;

function asyncMultiply(x, $y) {
    // Pause this function's execution for 100ms without
    // blocking the application's event loop.
    yield "pause" => 100;

    // The final value yielded by a generator with the "return"
    // key is used as the "return value" for that coroutine
    yield "return" => ($x * $y);
}

run(function() {
    try {
        // Yield control until the generator resolves.
        $result = (yield asyncMultiply(2, 21));
        var_dump($result); // int(42)
    } catch (Exception $e) {
        // If promise resolution fails the exception is
        // thrown back to us and we handle it as needed.
    }
});
```

As you can see in the above example there is no need for callbacks or `.then()` chaining. Instead,
we're able to use `yield` statements to control program flow even when future computational results
are still pending.

> **NOTE**
> 
> Any time a generator yields an `Amp\Promise` or a `Generator` there exists the possibility that the associated async operation(s) could fail. When this happens the appropriate exception is thrown back into the calling generator. Applications should generally wrap their promise yields in `try/catch` blocks as an error handling mechanism in this case.

### Coroutine Return Values

The resolution value of any yielded `Generator` is assigned inside that generator using the `"return"` yield key as shown here:

```php
use function Amp\run;

run(function() {
	$result = (yield myCoroutine());
	var_dump($result); // int(42)
});

function myCoroutine() {
    yield "pause" => 100;
    $asyncResult = (yield someAsyncCall());
    yield "return" => 42; // Assigns the "return" value
}
```

Coroutines may `yield "return"` multiple times if they like. Only the final value yielded with this key is returned from the coroutine in the original calling code. Note that coroutine generators may "return" an unresolved `Promise` and its eventual resolution will be used as the final return value upon completion:

```php
Amp\run(function() {
	// will resolve in three seconds with the eventual
	// value from myDelayedOperation()
	$result = (yield myCoroutine());
	var_dump($result); // int(42)
});

function myCoroutine() {
    yield "pause" => 1000;
    $myDelayedThingPromise = (yield myNestedCoroutine());
    yield "return" => $myDelayedThingPromise;
}

function myNestedCoroutine() {
    yield "pause" => 1000;
    yield "return" => myDelayedThing();
}

function myDelayedThing() {
    $promisor = new Amp\PrivateFuture;
    Amp\getReactor()->once(function() use ($promisor) {
        $promisor->succeed(42);
    }, $msDelay = 1000);

    return $promisor->promise();
}
```

> **IMPORTANT**
> 
> Because yielded generators are implicitly assumed to be co-routines applications **MUST** use the "return" key if they wish to return a generator from a coroutine without it being automatically resolved.


## Implicit Yield Behavior

Any value yielded without an associated string yield key is referred to as an "implicit" yield. All implicit yields must be one of the following two types ...

| Yieldable        | Description                                                                                                                                                                                                                      |
| -----------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise`    | Any promise instance may be yielded and control will be returned to the generator once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. If resolution succeeds the promise's resolved value is sent back into the generator. |
| `Generator`      | Any generator instance may be yielded. The resolution value returned to the original yielding generator is the final value yielded from the from the resolved generator using the `"return"` yield command. If an error occurs while resolving the nested generator it will be thrown back into the original yielding generator. |


> **IMPORTANT**
> 
> Any yielded value that is not an `Amp\Promise` or `Generator` will be treated as an **error** and an appropriate exception will be thrown back into the original yielding generator. This strict behavior differs from older versions of the library in which implicit yield values were simply sent back to the yielding generator function.

## Yield Command Reference

Command       | Description
------------- | ----------------------
| [pause](#yield-pause) | Pause generator execution for the yielded number of milliseconds |
| [immediately](#yield-immediately) | Resolve the yielded callback on the next iteration of the event loop |
| [once](#yield-once) | Resolve the yielded callback at array index 0 in array index 1 milliseconds |
| [repeat](#yield-repeat) | Repeatedly resolve the yielded callback at array index 0 every array index 1 milliseconds |
| [onreadable](#yield-onreadable) | Resolve the yielded callback at array index 1 when the stream resource at index 0 reports as readable |
| [onwritable](#yield-onwritable) | Resolve the yielded callback at array index 1 when the stream resource at index 0 reports as writable |
| [enable](#yield-enable) | Enable the yielded event watcher ID |
| [disable](#yield-disable) | Disable the yielded event watcher ID |
| [cancel](#yield-cancel) | Cancel the yielded event watcher ID |
| [all](#yield-all) | Flatten the array of promises/generators and return control when all individual elements resolve successfully; fail the result if any individual resolution fails |
| [any](#yield-any) | Flatten the array of promises/generators and return control when all individual elements resolve; never fail the result regardless of component failures |
| [some](#yield-some) | Flatten the array of promises/generators and return control when all individual elements resolve; only fail the result if all components fail |
| [bind](#yield-bind) | Bind a callable to the event reactor so it will be automagically resolved upon invocation |
| [nowait](#yield-nowait) | Don't wait on the yielded promise or generator to resolve before returning control to the generator |
| [async](#yield-async) | Syntactic sugar to make code more self-documenting. May be used only when yielding an `Amp\Promise` instance to await resolution. |
| [coroutine](#yield-coroutine) | Syntactic sugar to make code more self-documenting. May be used only when yielding a `Generator` instance for resolution (co-routine). |
| @ (prefix)  | Prefixed to another command to indicate the result should not be waited on before returning control to the generator |


## Yield Command Examples

### yield pause

```php
function() {
    // yield control for 100 milliseconds
    yield "pause" => 100;
};
```

### yield immediately

```php
function() {
    // Execute the specified $function in the next tick of the
    // event loop; the ID associated with this watcher is sent
    // back to the origin generator.
    $function = function($reactor, $watcherId){};
    $watcherId = (yield "immediately" => $function);
};
```

### yield once

```php
function() {
    // Schedule $function for execution in $msDelay milliseconds;
    // the ID associated with this watcher is sent back to the
    // origin generator.
    $function = function($reactor, $watcherId){};
    $msDelay = 100;
    $watcherId = (yield "once" => [$function, $msDelay]);
};
```

### yield repeat

```php
function() {
    // Schedule $function for execution every $msInterval milliseconds;
    // the ID associated with this watcher is sent back to the origin
    // generator where it can later be cancelled.
    $function = function($reactor, $watcherId){};
    $msInterval = 1000;
    $watcherId = (yield "repeat" => [$function, $msInterval]);
};
```

### yield onreadable

```php
function() {
    // Schedule $function for execution any time $stream has readable data.
    $function = function($reactor, $watcherId, $stream){};
    $stream = STDIN;
    $watcherId = (yield "onReadable" => [$stream, $function]);

    // We can also optionally disable stream watchers at registration time:
    $enableNow = false;
    $watcherId2 = (yield "onReadable" => [$stream, $function, $enableNow]);
};
```

### yield onwritable

```php
function() {
    // Schedule $function for execution any time $stream is writable.
    $function = function($reactor, $watcherId, $stream){};
    $stream = STDOUT;
    $watcherId = (yield "onWritable" => [$stream, $function]);

    // We can also optionally disable stream watchers at registration time:
    $enableNow = false;
    $watcherId2 = (yield "onWritable" => [$stream, $function, $enableNow]);
};
```

### yield enable

```php
function() {
    $stream = STDOUT;
    $function = function($reactor, $watcherId, $stream){};
    $enableNow = false;
    $watcherId = (yield "onWritable" => [$stream, $function, $enableNow]);

    // ... do some stuff ...

    // Lets enable the writability watcher now
    yield "enable" => $watcherId;
};
```

### yield disable

```php
function() {
    $watcherId = (yield "repeat" => [function(){}, 100]);

    // ... do some stuff ...

    // Disable (but don't cancel) our repeating timer watcher
    yield "disable" => $watcherId;
};
```

### yield cancel

```php
function() {
    $watcherId = (yield "repeat" => [function(){}, 100]);

    // ... do some stuff ...

    // Cancel our repeating timer watcher and free any associated resources
    yield "cancel" => $watcherId;
};
```

### yield all

```php
function myAsyncThing() {
    yield "pause" => 100;
    yield 44;
}

function() {
    // list()
    list($a, $b, $c) = (yield "all" => [
        42,
        new Amp\Success(43),
        myAsyncThing(),
	]);
    var_dump($a, $b, $c); // int(42), int(43), int(44)

    // extract()
    extract(yield "all" => [
        "d" => 42,
        "e" => new Amp\Success(43),
        "f" => myAsyncThing(),
	]);
    var_dump($d, $e, $f); // int(42), int(43), int(44)
};
```

### yield any

```php
function myAsyncThing() {
    yield "pause" => 100;
    yield 44;
}

function() {
    list($errors, $results) = (yield "any" => [
        "a" => 42,
        "b" => new Amp\Failure(new Exception("test")),
        "c" => myAsyncThing(),
	]);
	assert($errors["b"] instanceof Exception);
	assert($errors["b"]->getMessage() === "test");
	assert(isset($results["a"], $results["c"]));
	assert($results["a"] === 42);
	assert($results["c"] === 44);
};
```

### yield some

```php
function myAsyncThing() {
    yield "pause" => 100;
    yield 44;
}

function() {
    list($errors, $results) = (yield "some" => [
        "a" => 42,
        "b" => new Amp\Failure(new Exception("test")),
        "c" => myAsyncThing(),
	]);
	
	assert($results["a"] === 42);
	assert($errors["b"] instanceof Exception);
	assert($results["c"] === 44);
	
	try {
	    list($errors, $results) = (yield "some" => [
			new Amp\Failure(new Exception("ex1")),
			new Amp\Failure(new Exception("ex2")),
        ]);
        // You'll never reach this line because both promises failed
	} catch (Exception $e) {
		var_dump($e->getMessage());
    }
};
```

### yield bind

```php
function() {
    $repeatWatcherId = (yield "repeat" => [function(){}, 1000]);
    $func = function() use ($repeatWatcherId) {
        yield "cancel" => $repeatWatcherId;
    };

    $boundFunc = (yield "bind" => $func);
    
    // Resolved as if we yielded the "cancel" command here
    $boundFunc();
};
```

### yield nowait

```php
function myAsyncThing() {
    // pause for three seconds
    yield "pause" => 3000;
}

function() {
    // Don't wait for the async task to complete
    $startTime = time();
    yield "nowait" => myAsyncThing();
    var_dump(time() - $startTime); // int(0)

    // Wait for async task completion (normal)
    $startTime = time();
    yield myAsyncThing();
    var_dump(time() - $startTime); // int(3)
};
```

### yield async

```php
function myDelayedOperation() {
    // resolve the promise in three seconds
    $promisor = new Amp\PrivateFuture;
    Amp\getReactor()->once(function() use ($promisor) {
	    $promisor->succeed(42);
	}, $msDelay = 3000);

	return $promisor->promise();
}

function() {
    // Use the "async" key to document the operation
    $result = (yield "async" => myDelayedOperation());
    var_dump($result); // int(42)
};
```

### yield coroutine

```php
function myCoroutine($a) {
    $b = $a * 2;
    
    // pause for 100 milliseconds because we can
    yield "pause" => 100;
    
	$c = $b + 3;
	
    // assign the coroutine's return value
    yield "return" => $c;

	// replace the return with a different value
    yield "return" => 42;

    // we can optionally do more work after assigning a return
    yield someOtherAsyncOperation();
}

function() {
    // Use the "coroutine" key to document the operation
    $result = (yield "coroutine" => myCoroutine(5));
    var_dump($result); // int(42)
};
```