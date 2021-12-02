---
layout: "docs"
title: "Introduction"
permalink: "/"
---
Amp is a set of seamlessly integrated concurrency libraries for PHP based on [Revolt](https://revolt.run/). This package
provides futures and cancellations as a base for asynchronous programming.

Amp makes heavy use of fibers shipped with PHP 8.1 to write asynchronous code just like synchronous, blocking code. In
contrast to earlier versions, there's no need for generator based coroutines or callbacks. Similar to threads, each
fiber has its own call stack, but fibers are scheduled cooperatively by the event loop. Use `Amp\async()` to run things
concurrently.

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/amp
```

If you use this library, it's very likely you want to schedule events using [Revolt's event loop](https://revolt.run),
which you should require separately, even if it's automatically installed as a dependency.

```bash
composer require revolt/event-loop
```

## Preamble

The weak link when managing concurrency is humans; we simply don't think asynchronously or in parallel. Instead, we're
very good at doing one thing at a time and the world around us generally fits this model. So to effectively design for
concurrent processing in our code we have a couple of options:

1. Get smarter (not feasible);
2. Abstract concurrent task execution to make it feel synchronous.

## Contents

Amp provides [futures](./futures/README.md) and [cancellations](./cancellation/README.md) as building blocks for
(partially and fully) asynchronous libraries and applications. [Coroutines](./coroutines/README.md) make asynchronous
code feel as synchronous code.
