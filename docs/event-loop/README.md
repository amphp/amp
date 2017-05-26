---
layout: docs
title: Event Loop
permalink: /event-loop/
---
It may surprise people to learn that the PHP standard library already has everything we need to write event-driven and non-blocking applications. We only reach the limits of native PHP's functionality in this area when we ask it to poll thousands of file descriptors for IO activity at the same time. Even in this case, though, the fault is not with PHP but the underlying system `select()` call which is linear in its performance degradation as load increases.

For performance that scales out to high volume we require more advanced capabilities currently found only in extensions. If you wish to, for example, service 10,000 simultaneous clients in an Amp-backed socket server, you should use one of the event loop implementations based on a PHP extension. However, if you're using Amp in a strictly local program for non-blocking concurrency or you don't need to handle more than a few hundred simultaneous clients in a server application the native PHP functionality should be adequate.

## Global Accessor

Amp uses a global accessor for the event loop as there's only one event loop for each application. It doesn't make sense to have two loops running at the same time, as they would just have to schedule each other in a busy waiting manner to operate correctly.

The event loop should be accessed through the methods provided by `Amp\Loop`. On the first use of the accessor, Amp will automatically setup the best available driver, see next section.

`Amp\Loop::set()` can be used to set a custom driver or to reset the driver in tests, as each test should run with a fresh driver instance to achieve test isolation. In case of PHPUnit, you can use a [`TestListener` to reset the event loop](https://github.com/amphp/phpunit-util) automatically after each tests.

## Implementations

Amp offers different event loop implementations based on various backends. All implementations extend `Amp\Loop\Driver`. Each behaves exactly the same way from an external API perspective. The main differences have to do with underlying performance characteristics. The current implementations are listed here:

| Class                     | Extension                                              |
| ------------------------- | ------------------------------------------------------ |
| `Amp\Loop\NativeDriver`     | –                                                    |
| `Amp\Loop\EvDriver`         | [`pecl/ev`](https://pecl.php.net/package/ev)             |
| `Amp\Loop\EventDriver`      | [`pecl/event`](https://pecl.php.net/package/event) |
| `Amp\Loop\UvDriver`         | [`php-uv`](https://github.com/bwoebi/php-uv)             |

It's not important to choose one implementation for your application. Amp will automatically select the best available driver. It's perfectly fine to have one of the extensions in production while relying on the `NativeDriver` locally for development.

If you want to quickly switch implementations during development, e.g. for comparison or testing, you can set the `AMP_LOOP_DRIVER` environment variable to one of the classes. If you use a custom implementation, this only works if the implementation doesn't take any arguments.

## Event Loop as Task Scheduler

The first thing we need to understand to program effectively using an event loop is this:

**The event loop is our task scheduler.**

The event loop controls the program flow as long as it runs. Once we tell the event loop to run it will maintain control until the application errors out, has nothing left to do, or is explicitly stopped.

Consider this very simple example:

```php
<?php

require "vendor/autoload.php";

use Amp\Loop;

function tick() {
    echo "tick\n";
}

echo "-- before Loop::run()\n";

Loop::run(function() {
    Loop::repeat($msInterval = 1000, "tick");
    Loop::delay($msDelay = 5000, "Amp\\Loop::stop");
});

echo "-- after Loop::run()\n";
```

Upon execution of the above example you should see output like this:

```plain
-- before Loop::run()
tick
tick
tick
tick
-- after Loop::run()
```

This output demonstrates that what happens inside the event loop's run loop is like its own separate program. Your script will not continue past the point of `Loop::run()` unless there are no more scheduled events or `Loop::stop()` is invoked.

While an application can and often does take place entirely inside the confines of the run loop, we can also use the event loop to do things like the following example which imposes a short-lived timeout for interactive console input:

```php
<?php

use Amp\Loop;

$myText = null;

function onInput($watcherId, $stream) {
    global $myText;

    $myText = fgets($stream);
    stream_set_blocking(STDIN, true);

    Loop::cancel($watcherId);
    Loop::stop();
}

Loop::run(function () {
    echo "Please input some text: ";
    stream_set_blocking(STDIN, false);

    // Watch STDIN for input
    Loop::onReadable(STDIN, "onInput");

    // Impose a 5-second timeout if nothing is input
    Loop::delay($msDelay = 5000, "Amp\\Loop::stop");
});

var_dump($myText); // whatever you input on the CLI

// Continue doing regular synchronous things here.
```

Obviously we could have simply used `fgets(STDIN)` synchronously in this example. We're just demonstrating that it's possible to move in and out of the event loop to mix synchronous tasks with non-blocking tasks as needed.

Continue with the [Event Loop API](./api.md).
