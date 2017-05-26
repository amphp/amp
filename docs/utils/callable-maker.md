---
layout: docs
title: CallableMaker
permalink: /utils/callable-maker
---
`Amp\CallableMaker` is a helper trait that allows creating closures from private / protected static and instance methods in an easy way. Creating such callables might be necessary to register private / protected methods as callbacks in an efficient manner without making those methods public.

This trait should only be used in projects with a PHP 7.0 minimum requirement. If PHP 7.1 or later are the minimum requirement, `Closure::fromCallable` should be used directly.

## `callableFromInstanceMethod()`

Creates a `Closure` form an instance method with the given name and returns it. The closure can be passed around without worrying about the method's visibility.

## `callableFromStaticMethod()`

Same as `callableFromInstanceMethod()`, but for static methods.
