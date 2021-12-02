---
layout: docs title: Cancellation permalink: /cancellation/
---
Amp provides a `Cancellation` primitive to allow the cancellation of operations.

```php
request("...", new Amp\TimeoutCancellation(30));
```

```php
$deferredCancellation = new Amp\DeferredCancellation();
Loop::onSignal(SIG_INT, function () use ($deferredCancellation) {
    $deferredCancellation->cancel();
});

request("...", $deferredCancellation->getCancellation());
```

Every operation that supports cancellation accepts an instance of `Cancellation` as (optional)
argument. `$cancellation->throwIfRequested()` can be used to fail the operation with a `CancelledException`.
As `$cancellation` is often an optional parameter and might be `null`, these calls need to be guarded with
a `if ($cancellation)` or similar check. Instead of doing so, it's often easier to simply set the token
to `$cancellation ??= new NullCancellationToken` at the beginning of the method.

While `throwIfRequested()` works well, some operations might want to subscribe with a callback instead. They can do so
using `Cancellation::subscribe()` to subscribe any cancellation requests that might happen.

If the operation consists of any sub-operations that support cancellation, it passes that same `Cancellation`
instance down to these sub-operations.

The original caller creates a `Cancellation` by creating an instance of `DeferredCancellation` and
passing `$deferred->getCancellation()` to the operation as shown in the above example, or using one of the other
implementations of `Cancellation`, such as `TimeoutCancellation`. Only the original caller has access to
the `DeferredCancellation` and can cancel the operation using `DeferredCancellation::cancel()`, similar to the way it
works with `DeferredFuture` and `Future`.

{:.note}
> Cancellations are advisory only. A DNS resolver might ignore cancellation requests after the query has been sent as the response has to be processed anyway and can still be cached. An HTTP client might continue a nearly finished HTTP request to reuse the connection, but might abort a chunked encoding response as it cannot know whether continuing is actually cheaper than aborting.
