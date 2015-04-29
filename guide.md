The Amp Guide
=============

Amp is a non-blocking concurrency framework for PHP applications

**About This Document**

If you're reading The Amp Guide as a github markdown file you should click the link below to view the styled HTML version. The github markdown display is really unflattering and misses out on features the Table of Contents. Please don't subject your eyes to this monstrosity; click here instead:

[**Show me the Amp Guide the way nature intended!**](https://stackedit.io/viewer#!url=https://raw.githubusercontent.com/amphp/amp/master/guide.md)

**Dependencies**

- PHP 7

Optional PHP extensions may be used to improve performance in production environments and react to process control signals:

- [php-uv](https://github.com/chobie/php-uv) extension for libuv backends

**Installation**


*Git:*
```bash
$ git clone https://github.com/amphp/amp.git
```

*Composer:*
```bash
$ composer require amphp/amp
```


# Table of Contents

[TOC]

---

# Event Reactor Concepts

## Reactor Implementations

It may surprise people to learn that the PHP standard library already has everything we need to write event-driven and non-blocking applications. We only reach the limits of native PHP's functionality in this area when we ask it to poll several hundred streams for read/write capability at the same time. Even in this case, though, the fault is not with PHP but the underlying system `select()` call which is linear in its performance  degradation as load increases.

For performance that scales out to high volume we require more advanced capabilities currently found only in extensions. If you wish to, for example, service 10,000 simultaneous clients in an Amp-backed socket server you would definitely need to use one of the reactors based on a PHP extension. However, if you're using Amp in a strictly local program for non-blocking concurrency or you don't need to handle more than ~100 or so simultaneous clients in a server application the native PHP functionality is perfectly adequate.

Amp currently exposes two separate implementations for its standard `Reactor` interface. Each behaves exactly the same way from an external API perspective. The main differences have to do with underlying performance characteristics. The one capability that the extension-based reactors do offer that's unavailable with the native implementation is the ability to watch for process control signals. The current implementations are listed here:

| Class                 | Extension                                             |
| --------------------- | ----------------------------------------------------- |
| Amp\NativeReactor     | n/a                                                   |
| Amp\UvReactor         | [php-uv](https://github.com/chobie/php-uv)            |


## Reactor == Task Scheduler

The first thing we need to understand to program effectively using an event loop is this:

> *The event reactor is our task scheduler.*

The reactor controls program flow as long as it runs. Once we tell the reactor to run it will
control program flow until the application errors out, has nothing left to do, or is explicitly
stopped. Consider this very simple example:

```php
<?php

function tick() {
    echo "tick\n";
}

echo "before run()\n";

Amp\run(function() {
    Amp\repeat("tick", $msInterval = 1000);
    Amp\once("Amp\stop", $msDelay = 5000);
});

echo "after stop()\n";
```

Upon execution of the above example you should see output like this:

```
before run()
tick
tick
tick
tick
tick
after stop()
```

Hopefully this output demonstrates the concept that what happens inside the event reactor's run loop is like its own separate program. Your script will not continue past the point of `Reactor::run()` unless one of the there are no more scheduled events or `Reactor::stop()` is invoked.

While an application can and often does take place entirely inside the confines of the run loop, we can also use the reactor to do things like the following example which imposes a short-lived timeout for interactive console input:

```php
<?php
$myText = null;

function main(Amp\Reactor $reactor) {
	echo "Please input some text: ";
	stream_set_blocking(STDIN, false);

	// Watch STDIN for input
    $reactor->onReadable(STDIN, "onInput");

	// Impose a 5-second timeout if nothing is input
    $reactor->once("Amp\stop", $msDelay = 5000);
}

function onInput(Amp\Reactor $reactor, string $watcherId) {
	global $myText;
    $myText = fgets(STDIN);
    $reactor->cancel($watcherId);
    stream_set_blocking(STDIN, true);
    $reactor->stop();
}

Amp\run("main");
var_dump($myText); // whatever you input on the CLI

// Continue doing regular synchronous things here.
```

Obviously we could have simply used `fgets(STDIN)` synchronously in this example. We're just demonstrating that it's possible to move in and out of the event loop to mix synchronous tasks with non-blocking tasks as needed.


## The Universal Reactor

In the above example we use the reactor's object API to manipulate event watchers watchers.  Though it's possible to do so, note that it almost *never* makes sense to instantiate and run multiple event loop instances in a single-threaded process. 

> **IMPORTANT**
> 
> Bugs arising from the existence of multiple reactor instances are exceedingly difficult to debug. The reason for this should be relatively clear: running one event loop will block script execution and prevent others from executing at the same time. This sort of "loop starvation" results in events that inexplicably fail to trigger. You should endeavor to always use the same reactor instance in your application. Because the event loop is often a truly global feature of an application the procedural API functions use a static instance to ensure the same `Reactor` is reused. Be careful about instantiating reactors manually and mixing in calls to the function API.

@TODO Discuss using `Reactor::__debugInfo()` to check the number of reactor instances

@TODO Discuss the `Amp\getReactor()` singleton function

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

`enable()` is the diametric analog of the `disable()` example demonstrated above:

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

### Optional Watcher Settings

| Option              | Description                                       |
| ------------------- | --------------------------------------------------|
| `"enable"`          | All watchers are enabled by default. Passing the `"enable"` option with a falsy value will create a watcher in a disabled state. |
| `"msDelay"`         | Used with `repeat()` watchers to specify a different millisecond timeout for the initial callback invocation. If not specified, repeating timer watchers wait until the `$msInterval` expires before their initial invocation. |
| `"callbackData"`    | Optional user data to pass as the final parameter when invoking the watcher callback. If this option is unspecified a callback receives `null` as its final argument. |

### Watcher Callback Parameters

Watcher callbacks are invoked using the following standardized parameter order:

| Watcher Type          | Callback Signature                                |
| --------------------- | --------------------------------------------------|
| immediately()         | `function(Reactor $reactor, string $watcherId, $callbackData)`          |
| once()                | `function(Reactor $reactor, string $watcherId, $callbackData)`          |
| repeat()              | `function(Reactor $reactor, string $watcherId, $callbackData)` |
| onReadable()          | `function(Reactor $reactor, string $watcherId, $stream, $callbackData)` |
| onWritable()          | `function(Reactor $reactor, string $watcherId, $stream, $callbackData)` |
| onSignal()            | `function(Reactor $reactor, string $watcherId, $signo, $callbackData)`  |


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

A standard pattern in this area is to initialize writability watchers in a disabled state before subsequently enabling them at a later time as shown here:

```php
<?php
$reactor = new Amp\NativeReactor;
$options = ["enable" => false];
$watcherId = $reactor->onWritable(STDOUT, function(){}, $options);
// ...
$reactor->enable($watcherId);
// ...
$reactor->disable($watcherId);
```


### Process Signal Number Availability

Using the `SignalReactor` interface is relatively straightforward with the php-uv extension because
it exposes `UV::SIG*` constants for watchable signals. Applications using the `LibeventReactor` to
will need to manually specify the appropriate integer signal numbers when registering signal watchers.


[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"


### Timer Drift

@TODO Discuss how repeating timer watchers are rescheduled from `$timestampAtTickStart + $watcherMsInterval` and are not subject to drift but *may* stack up if executing very slow tasks with insufficiently low intervals in-between invocations.

### Avoiding Memory Leaks

@TODO Discuss cancelling persistent watchers (i.e. repeat/onReadable/onWritable)

### Debugging Amp Applications

@TODO Discuss `__debugInfo()`

# Managing Concurrency

The weak link when managing concurrency is humans; we simply don't think asynchronously or in parallel. Instead, we're really good at doing one thing at a time and the world around us generally fits this model. So to effectively design for concurrent processing in our code we have a couple of options:

1. Get smarter (not feasible);
2. Abstract concurrent task execution to make it feel synchronous.

## Promises

The basic unit of concurrency in an Amp application is the `Amp\Promise`. These objects should be thought of as "placeholders" for values or tasks that aren't yet complete. By using placeholders we're able to reason about the results of concurrent operations as if they were already complete variables.

> **NOTE**
> 
> Amp promises do *not* conform to the "Thenables" abstraction common in javascript promise implementations. It is this author's opinion that chaining .then() calls is a suboptimal method for avoiding callback hell in a world with generator coroutines. Instead, Amp utilizes PHP generators to "synchronize" concurrent task execution.

### The Promise API

```php
interface Promise {
    public function when(callable $func);
    public function watch(callable $func);
}
```

In its simplest form the `Amp\Promise` aggregates callbacks for dealing with computational results once they eventually resolve. While most code will not interact with this API directly thanks to the magic of [Generators](#generators), let's take a quick look at the two simple API methods exposed on `Amp\Promise` implementations:


| Method                | Callback Signature                              |
| --------------------- | ------------------------------------------------|
| void when(callable)   | `function(Exception $error = null, $result = null)` |
| void watch(callable)  | `function($data)`                                   |


### when()

`Amp\Promise::when()` accepts an error-first callback. This callback is responsible for reacting to the eventual result of the computation represented by the promise placeholder. For example:

```php
<?php
$promise = someFunctionThatReturnsAPromise();
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

> **NOTE**
>
> `Promise::watch()` updates are variadic in nature. Producers may send consumers as many arguments as they require in any given notification.

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

### any()

@TODO

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

function asyncMultiply($x, $y) {
    yield new Amp\Pause($millisecondsToPause = 100);
    return ($x * $y);
}

Amp\run(function() {
    try {
        // Yield control until the generator resolves
        // and return its eventual result.
        $result = yield from asyncMultiply(2, 21); // int(42)
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
> Any time a generator yields an `Amp\Promise` there exists the possibility that the associated async operation(s) could fail. When this happens the appropriate exception is thrown back into the calling generator. Applications should generally wrap their promise yields in `try/catch` blocks as an error handling mechanism in this case.

### Subgenerators

@TODO Discuss `yield from`

### Implicit Yield Behavior

Any value yielded without an associated string yield key is referred to as an "implicit" yield. All implicit yields must be one of the following two types ...

| Yieldable        | Description                                                                                                                                                                                                                      |
| -----------------| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `Amp\Promise`    | Any promise instance may be yielded and control will be returned to the generator once the promise resolves. If resolution fails the relevant exception is thrown into the generator and must be handled by the application or it will bubble up. If resolution succeeds the promise's resolved value is sent back into the generator. |
| `null`      | @TODO |


> **IMPORTANT**
> 
> Any yielded value that is not an `Amp\Promise` or `Generator` will be treated as an **error** and an appropriate exception will be thrown back into the original yielding generator. This strict behavior differs from older versions of the library in which implicit yield values were simply sent back to the yielding generator function.


### Extending Coroutine Resolution

@TODO Discuss custom promisifier callables
