<?php

namespace Amp;

class Combinator {
    private $reactor;

    /**
     * @param \Amp\Reactor $reactor
     */
    public function __construct(Reactor $reactor = null) {
        $this->reactor = $reactor ?: reactor();
    }

    /**
     * If any one of the Promises fails the resulting Promise will fail. Otherwise
     * the resulting Promise succeeds with an array matching keys from the input array
     * to their resolved values.
     *
     * @param array[\Amp\Promise] $promises
     * @return \Amp\Promise
     */
    public function all(array $promises) {
        if (empty($promises)) {
            return new Success([]);
        }

        $results = [];
        $count = count($promises);
        $future = new Future($this->reactor);
        $done = false;

        foreach ($promises as $key => $promise) {
            $promise = ($promise instanceof Promise) ? $promise : new Success($promise);
            $promise->when(function($error, $result) use (&$count, &$results, $key, $future, &$done) {
                if ($done) {
                    // If the future already failed we don't bother.
                    return;
                }
                if ($error) {
                    $done = true;
                    $future->fail($error);
                    return;
                }

                $results[$key] = $result;
                if (--$count === 0) {
                    $done = true;
                    $future->succeed($results);
                }
            });
        }

        return $future->promise();
    }

    /**
     * Resolves with a two-item array delineating successful and failed Promise results.
     *
     * The resulting Promise will only fail if ALL of the Promise values fail or if the
     * Promise array is empty.
     *
     * The resulting Promise is resolved with an indexed two-item array of the following form:
     *
     *     [$arrayOfFailures, $arrayOfSuccesses]
     *
     * The individual keys in the resulting arrays are preserved from the initial Promise array
     * passed to the function for evaluation.
     *
     * @param array[\Amp\Promise] $promises
     * @return \Amp\Promise
     */
    public function some(array $promises) {
        if (empty($promises)) {
            return new Failure(new \LogicException(
                'No promises or values provided'
            ));
        }

        $results = $errors = [];
        $count = count($promises);
        $future = new Future($this->reactor);

        foreach ($promises as $key => $promise) {
            $promise = ($promise instanceof Promise) ? $promise : new Success($promise);
            $promise->when(function($error, $result) use (&$count, &$results, &$errors, $key, $future) {
                if ($error) {
                    $errors[$key] = $error;
                } else {
                    $results[$key] = $result;
                }

                if (--$count > 0) {
                    return;
                } elseif (empty($results)) {
                    $future->fail(new \RuntimeException(
                        'All promises failed'
                    ));
                } else {
                    $future->succeed([$errors, $results]);
                }
            });
        }

        return $future->promise();
    }

    /**
     * Resolves with the first successful Promise value. The resulting Promise will only fail if all
     * Promise values in the group fail or if the initial Promise array is empty.
     *
     * @param array[\Amp\Promise] $promises
     * @return \Amp\Promise
     */
    public function first(array $promises) {
        if (empty($promises)) {
            return new Failure(new \LogicException(
                'No promises or values provided'
            ));
        }

        $count = count($promises);
        $done = false;
        $future = new Future($this->reactor);

        foreach ($promises as $promise) {
            $promise = ($promise instanceof Promise) ? $promise : new Success($promise);
            $promise->when(function($error, $result) use (&$count, &$done, $future) {
                if ($done) {
                    // we don't care about Futures that resolve after the first
                    return;
                } elseif ($error && --$count === 0) {
                    $future->fail(new \RuntimeException(
                        'All promises failed'
                    ));
                } elseif (empty($error)) {
                    $done = true;
                    $this->succeed($result);
                }
            });
        }

        return $future->promise();
    }

    /**
     * Map future values using the specified callable
     *
     * @param array $promises
     * @param callable $func
     * @return \Amp\Promise
     */
    public function map(array $promises, callable $func) {
        if (empty($promises)) {
            return new Success([]);
        }

        $results = [];
        $count = count($promises);
        $future = new Future($this->reactor);
        $done = false;

        foreach ($promises as $key => $promise) {
            $promise = ($promise instanceof Promise) ? $promise : new Success($promise);
            $promise->when(function($error, $result) use (&$count, &$results, $key, $future, $func, &$done) {
                if ($done) {
                    // If the future already failed we don't bother.
                    return;
                }
                if ($error) {
                    $done = true;
                    $future->fail($error);
                    return;
                }

                $results[$key] = $func($result);
                if (--$count === 0) {
                    $future->succeed($results);
                }
            });
        }

        return $future->promise();
    }

    /**
     * Filter future values using the specified callable
     *
     * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
     * discarded. Array keys are retained for any results not filtered out by the functor.
     *
     * @param array $promises
     * @param callable $func
     * @return \Amp\Promise
     */
    public function filter(array $promises, callable $func) {
        if (empty($promises)) {
            return new Success([]);
        }

        $results = [];
        $count = count($promises);
        $future = new Future($this->reactor);
        $done = false;

        foreach ($promises as $key => $promise) {
            $promise = ($promise instanceof Promise) ? $promise : new Success($promise);
            $promise->when(function($error, $result) use (&$count, &$results, $key, $future, $func, &$done) {
                if ($done) {
                    // If the future result already failed we don't bother.
                    return;
                }
                if ($error) {
                    $done = true;
                    $future->fail($error);
                    return;
                }
                if ($func($result)) {
                    $results[$key] = $result;
                }
                if (--$count === 0) {
                    $future->succeed($results);
                }
            });
        }

        return $future->promise();
    }
}
