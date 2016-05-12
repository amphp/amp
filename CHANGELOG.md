### 1.2.2

- Fix notice in NativeReactor when removing a handle while
  an event is waiting for it. (Regression fix from 1.1.1)

### 1.2.1

- Fix uv_run() potentially exiting earlier than intended,
  leading to an infinite loop in UvReactor::run().

1.2.0
-----

- resolve() now also accepts callables returning Generators.

### 1.1.1

- Fix memory leak in NativeReactor, retaining an empty array
  for each stream.
- Remove circular references in UvReactor to avoid garbage
  collector calls.

1.1.0
-----

- Add getExceptions() method to CombinatorException to get an
  array of all the exceptions (affecting some() and first()).
- Fix NativeReactor not ending up in stopped state if primary
  callback didn't install any events.

### 1.0.8

- Fix NativeReactor running a busy loop if no timers are active.
  Properly block now in NativeReactor inside stream_select().

### 1.0.7

- Several combinator functions could result in a Promise already
  resolved exception in case some values of the array weren't
  Promises.

### 1.0.6

- Fix issue in NativeReactor causing `stop()` to be delayed by
  one second.

### 1.0.5

- Convert general `RuntimeException` to more specific
  `Amp\CombinatorException`.

### 1.0.4

- Repeat watchers in LibeventReactor internally were handled in
  microsecond intervals instead of milliseconds.

### 1.0.3

- Fix issue in NativeReactor capable of causing keep alive
  counter corruption when a watcher was cancelled inside its
  own callback.
- Fix issue in UvReactor with libuv >= 1.1.0 causing busy loop
  with immediates present, but no watchers being triggered.

### 1.0.2

- Fix PHP7 issue in which top-level Throwables weren't caught
  in certain coroutine contexts.
- Remove error suppression operator on optionally null option
  assignment to avoid spurious E_NOTICE output when custom
  error handlers are used.

### 1.0.1

- Fix bug preventing UvReactor::tick() from returning when no
  events are ready for a single active IO watcher.

1.0.0
-----

- Initial stable API release
