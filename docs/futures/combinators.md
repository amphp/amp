---
layout: "docs"
title: "Future Combinators"
permalink: "/futures/combinators"
---
Amp provides a set of helper functions to deal with multiple futures and combining them.

## `await()`

`Amp\Future\await()` awaits all `Future` objects of an `iterable`. If one of the `Future` instances errors, the operation
will be aborted with that exception. Otherwise, the result is an array matching keys from the input `iterable` to their
resolved values.

The `await()` combinator is extremely powerful because it allows us to concurrently execute many asynchronous operations
at the same time. Let's look at a simple example using [`amphp/http-client`](https://github.com/amphp/http-client) to
retrieve multiple HTTP resources concurrently:

```php
<?php

use Amp\Future;

$httpClient = HttpClientBuilder::buildDefault();
$uris = [
    "google" => "https://www.google.com",
    "news"   => "https://news.google.com",
    "bing"   => "https://www.bing.com",
    "yahoo"  => "https://www.yahoo.com",
];

try {
    $responses = Future\await(array_map(function ($uri) use ($httpClient) {
        return $httpClient->request(new Request($uri, 'HEAD'));
    }, $uris));

    foreach ($responses as $key => $response) {
        printf(
            "%s | HTTP/%s %d %s\n",
            $key,
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );
    }
} catch (Amp\CompositeException $e) {
    // If any one of the requests fails the combo will fail
    echo $e->getMessage(), "\n";
}
```

## `awaitAnyN()`

`Amp\Future\awaitAnyN()` is the same as `await()` except that it tolerates individual failures. A result is returned once
exactly `$count` instances in the `iterable` complete successfully. The return value is an array of values. The
individual keys in the component array are preserved from the `iterable` passed to the function for evaluation.

## `awaitAll()`

`Amp\Promise\awaitAll()` awaits all futures and returns their results as `[$errors, $values]` array.

## `awaitFirst()`

`Amp\Promise\awaitFirst()` unwraps the first completed `Future`, whether successfully completed or errored.

## `awaitAny()`

`Amp\Promise\awaitAny()` unwraps the first successfully completed `Future`.
