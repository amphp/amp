---
layout: docs
title: Iterator Combination
permalink: /iterators/combinators
---
Amp provides two common combination helpers for iterators: `concat` and `merge`.

## `concat()`

`concat()` accepts an array of `Iterator` instances and concatenates the given iterators into a single iterator, emitting values from a single iterator at a time. The prior iterator must complete before values are emitted from any subsequent iterators. Iterators are concatenated in the order given (iteration order of the array).

## `merge()`

`merge()` accepts an array of `Iterator` instances and creates an `Iterator` that emits values emitted from any iterator in the array of iterators ending once all emitters completed.
