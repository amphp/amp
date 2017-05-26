---
layout: docs
title: Iterators
permalink: /iterators/
---
Iterators are the next level after promises. While promises resolve once and with one value, iterators allow a set of items to be consumed.

## Iterator Consumption

Every iterator in Amp follows the `Amp\Iterator` interface.

```php
namespace Amp;

interface Iterator {
    public function advance(): Promise;
    public function getCurrent();
}
```

`advance()` returns a `Promise` and its resolution value tells whether there's an element to consume or not. If it resolves to `true`, `getCurrent()` can be used to consume the element at the current position, otherwise the iterator ended and there are no more values to consume. In case an exception happens, `advance()` returns a failed promise and `getCurrent()` throws the failure reason when called.

### Simple Consumption Example

```php
$iterator = foobar();

while (yield $iterator->advance()) {
    $element = $iterator->getCurrent();
    // do something with $element
}
```

## Iterator Creation

### Emitter

What `Deferred` is for promises, is `Emitter` for iterators. A library that returns an `Iterator` for asynchronous consumption of an iterable result creates an `Amp\Emitter` and returns the `Iterator` using `iterate()`. This ensures a consumer can only consume the iterator, but not emit values or complete the iterator.

#### `emit()`

`emit()` emits a new value to the `Iterator`, which can be consumed by a consumer. The emitted value is passed as first argument to `emit()`. `emit()` returns a `Promise` that can be waited on before emitting new values. This allow emitting values just as fast as the consumer can consume them.

#### `complete()`

`complete()` marks the `Emitter` / linked `Iterator` as complete. No further emits are allowed after completing an `Emitter` / `Iterator`. 

### Producer

`Producer` is a simplified form of `Emitter` that can be used when a single coroutine can emit all values.

`Producer` accepts a `callable` as first constructor parameter that gets run as a coroutine and passed an `$emit` callable that can be used to emit values just like the `emit()` method does in `Emitter`.

#### Example

```php
$iterator = new Producer(function (callable $emit) {
    yield $emit(1);
    yield $emit(new Delayed(500, 2));
    yield $emit(3);
    yield $emit(4);
});
```

### `fromIterable`

Iterators can also be created from ordinary PHP arrays or `Traversable` instances, which is mainly useful in tests, but might also be used for the same reasons as `Success` and `Failure`.

```php
function fromIterable($iterable, int $delay = 0) { ... }
```

`$delay` allows adding a delay between each emission.
