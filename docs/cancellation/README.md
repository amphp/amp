---
layout: docs
title: Cancellation
permalink: /cancellation/
---
Amp provides primitives to allow the cancellation of operations, namely `CancellationTokenSource` and `CancellationToken`.

Every operation that supports cancellation accepts an instance of `CancellationToken` as (optional) argument. The operation then subscribes with `CancellationToken::subscribe()` to any cancellation requests that might happen. If the operation consists of any sub-operations that support cancellation, it passes that same `CancellationToken` instance down to these sub-operations.
  
The original caller creates a `CancellationToken` by creating an instance of `CancellationTokenSource` and passing `$cancellationTokenSource->getToken()` to the operation. Only the original caller has access to the `CancellationTokenSource` and can cancel the operation using `CancellationTokenSource::cancel()`.

{:.note}
> Cancellations are advisory only and have don't care semantics. A DNS resolver might ignore cancellation requests after the query has been sent as the response has to be processed anyway and can still be cached. An HTTP client might continue a nearly finished HTTP request to reuse the connection, but might abort a chunked encoding response as it cannot know the end.
