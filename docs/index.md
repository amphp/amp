---
layout: docs
title: Introduction
permalink: /
---
The weak link when managing concurrency is humans; we simply don't think asynchronously or in parallel. Instead, we're really good at doing one thing at a time and the world around us generally fits this model. So to effectively design for concurrent processing in our code we have a couple of options:

1. Get smarter (not feasible);
2. Abstract concurrent task execution to make it feel synchronous.

Amp provides an [event loop](./event-loop/README.md), [promises](./promises/README.md) and [asynchronous iterators](./iterators/README.md) as building blocks for (fully) asynchronous libraries and applications. [Coroutines](./coroutines/README.md) make asynchronous code feel as synchronous as synchronous code.

Start with the [Introduction to Event Loops](./event-loop/).
