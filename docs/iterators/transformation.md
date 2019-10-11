---
layout: docs
title: Iterator Transformation
permalink: /iterators/transformation
---
Amp provides some common transformation helpers for iterators: `map`, `filter` and `toArray`.

Further primitives are very easy to implement using `Producer` with these as examples.

## `map()`

`map()` accepts an `Iterator` and a `callable` `$onEmit` that can transform each value into another value.

## `filter()`

`filter()` accepts an `Iterator` and a `callable` `$filter`. If `$filter($value)` returns `false` the value gets filtered, otherwise the value is retained in the resulting `Iterator`.

## `toArray()`

`toArray()` accepts an `Iterator` and returns a `Promise` which resolves to an array of all the items from the iterator.
