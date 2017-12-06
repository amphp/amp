---
layout: docs
title: Promise Combinators
permalink: /promises/combinators
---
Multiple promises can be combined into a single promise using different functions.

## `all()`

`Amp\Promise\all()` combines an array of promise objects into a single promise that will resolve
when all promises in the group resolve. If any one of the `Amp\Promise` instances fails the
combinator's `Promise` will fail. Otherwise the resulting `Promise` succeeds with an array matching
keys from the input array to their resolved values.

The `all()` combinator is extremely powerful because it allows us to concurrently execute many
asynchronous operations at the same time. Let's look at a simple example using the Amp HTTP client
([Artax](https://github.com/amphp/artax)) to retrieve multiple HTTP resources concurrently:

```php
<?php

use Amp\Loop;
use Amp\Promise;

Loop::run(function () {
    $httpClient = new Amp\Artax\DefaultClient;
    $uris = [
        "google" => "http://www.google.com",
        "news"   => "http://news.google.com",
        "bing"   => "http://www.bing.com",
        "yahoo"  => "https://www.yahoo.com",
    ];

    try {
        // magic combinator sauce to flatten the promise
        // array into a single promise.
        // yielding an array is an implicit "yield Amp\Promise\all($array)".
        $responses = yield array_map(function ($uri) use ($httpClient) {
            return $httpClient->request($uri);
        }, $uris);

        foreach ($responses as $key => $response) {
            printf(
                "%s | HTTP/%s %d %s\n",
                $key,
                $response->getProtocolVersion(),
                $response->getStatus(),
                $response->getReason()
            );
        }
    } catch (Amp\MultiReasonException $e) {
        // If any one of the requests fails the combo will fail and
        // be thrown back into our generator.
        echo $e->getMessage(), "\n";
    }

    Loop::stop();
});
```

## `some()`

`Amp\Promise\some()` is the same as `all()` except that it tolerates individual failures. As long
as at least one promise in the passed succeeds, the combined promise will succeed. The successful
resolution value is an array of the form `[$arrayOfErrors, $arrayOfValues]`. The individual keys
in the component arrays are preserved from the promise array passed to the functor for evaluation.

## `any()`

`Amp\Promise\any()` is the same as `some()` except that it tolerates all failures. It will succeed even if all promises failed.

## `first()`

`Amp\Promise\first()` resolves with the first successful result. The resulting promise will only fail if all
promises in the group fail or if the promise array is empty.
