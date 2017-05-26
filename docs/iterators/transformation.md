---
layout: docs
title: Iterator Transformation
permalink: /iterators/transformation
---
Amp provides two common transformation helpers for iterators: `map` and `filter`.

Further primitives are very easy to implement using `Producer` with those two as examples.

## `map()`

`map()` accepts an `Iterator` and a `callable` `$onEmit` that can transform each value into another value.

## `filter()`

`filter()` accepts an `Iterator` and a `callable` `$filter`. If `$filter($value)` returns `false` the value gets filtered, otherwise the value is retained in the resulting `Iterator`.
