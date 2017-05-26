---
layout: docs
title: Introduction
permalink: /
---
Amp is a non-blocking concurrency framework for PHP. It provides an event loop, promises and streams as a base for asynchronous programming.

Promises in combination with generators are used to build coroutines, which allow writing asynchronous code just like synchronous code, without any callbacks.

## Installation

```
composer require amphp/amp
```

## Preamble

The weak link when managing concurrency is humans; we simply don't think asynchronously or in parallel. Instead, we're really good at doing one thing at a time and the world around us generally fits this model. So to effectively design for concurrent processing in our code we have a couple of options:

1. Get smarter (not feasible);
2. Abstract concurrent task execution to make it feel synchronous.

## Contents

Amp provides an [event loop](./event-loop/README.md), [promises](./promises/README.md) and [asynchronous iterators](./iterators/README.md) as building blocks for (fully) asynchronous libraries and applications. [Coroutines](./coroutines/README.md) make asynchronous code feel as synchronous as synchronous code.

Start with the [Introduction to Event Loops](./event-loop/).
