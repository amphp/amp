---
layout: docs
title: Cancellation
permalink: /cancellation/
---
Amp provides primitives to allow the cancellation of operations, namely `CancellationTokenSource` and `CancellationToken`.

```php
$tokenSource = new CancellationTokenSource;
$promise = asyncRequest("...", $tokenSource->getToken());

Loop::delay(1000, function () use ($tokenSource) {
    $tokenSource->cancel();
});

$result = yield $promise;
```

Every operation that supports cancellation accepts an instance of `CancellationToken` as (optional) argument. Within a coroutine, `$token->throwIfRequested()` can be used to fail the operation with a `CancelledException`. As `$token` is often an optional parameter and might be `null`, these calls need to be guared with a `if ($token)` or similar check. Instead of doing so, it's often easier to simply set the token to `$token = $token ?? new NullCancellationToken` at the beginning of the method.

While `throwIfRequested()` works well within coroutines, some operations might want to subscribe with a callback instead. They can do so using `CancellationToken::subscribe()` to subscribe any cancellation requests that might happen.

If the operation consists of any sub-operations that support cancellation, it passes that same `CancellationToken` instance down to these sub-operations.
  
The original caller creates a `CancellationToken` by creating an instance of `CancellationTokenSource` and passing `$cancellationTokenSource->getToken()` to the operation as shown in the above example. Only the original caller has access to the `CancellationTokenSource` and can cancel the operation using `CancellationTokenSource::cancel()`, similar to the way it works with `Deferred` and `Promise`.

{:.note}
> Cancellations are advisory only. A DNS resolver might ignore cancellation requests after the query has been sent as the response has to be processed anyway and can still be cached. An HTTP client might continue a nearly finished HTTP request to reuse the connection, but might abort a chunked encoding response as it cannot know whether continuing is actually cheaper than aborting.
