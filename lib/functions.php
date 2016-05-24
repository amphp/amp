<?php

namespace Amp;

use Interop\Async\Loop;

/**
 * Throttles an observable to only emit a value every $interval milliseconds.
 *
 * @param \Amp\Observable $observable
 * @param int $interval
 *
 * @return \Amp\Observable
 */
function throttle(Observable $observable, $interval) {
    if (0 >= $interval) {
        throw new \InvalidArgumentException("The interval should be greater than 0");
    }

    return new Emitter(function (callable $emit) use ($observable, $interval) {
        $iterator = $observable->getIterator();
        $start = (int) (\microtime(true) - $interval);

        while (yield $iterator->isValid()) {
            $diff = $interval + $start - (int) (\microtime(true) * 1e3);

            if (0 < $diff) {
                yield new Pause($diff);
            }

            $start = (int) (\microtime(true) * 1e3);

            yield $emit($iterator->getCurrent());
        }

        yield Coroutine::result($iterator->getReturn());
    });
}

/**
 * Creates an observable that emits values emitted from any observable in the array of observables. Values in the
 * array are passed through the from() function, so they may be observables, arrays of values to emit, awaitables,
 * or any other value.
 *
 * @param \Amp\Observable[] $observables
 *
 * @return \Amp\Observable
 */
function merge(array $observables) {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \InvalidArgumentException("Non-observable provided");
        }
    }

    return new Emitter(function (callable $emit) use ($observables) {
        $generator = function (Observable $observable) use (&$emitting, $emit) {
            $iterator = $observable->getIterator();

            while (yield $iterator->isValid()) {
                while ($emitting !== null) {
                    yield $emitting; // Prevent simultaneous emit.
                }

                yield $emitting = $emit($iterator->getCurrent());
                $emitting = null;
            }

            yield Coroutine::result($iterator->getReturn());
        };

        /** @var \Amp\Coroutine[] $coroutines */
        $coroutines = [];

        foreach ($observables as $observable) {
            $coroutines[] = new Coroutine($generator($observable));
        }

        yield Coroutine::result(yield all($coroutines));
    });
}

/**
 * Returns an observable that emits a value every $interval milliseconds after the previous value has been consumed
 * (up to $count times (or indefinitely if $count is 0). The value emitted is an integer of the number of times the
 * observable emitted a value.
 *
 * @param int $interval Time interval between emitted values in milliseconds.
 * @param int $count Use 0 to emit values indefinitely.
 *
 * @return \Amp\Observable
 */
function interval($interval, $count = 0) {
    $count = (int) $count;
    if (0 > $count) {
        throw new \InvalidArgumentException("The number of times to emit must be a non-negative value");
    }

    return new Emitter(function (callable $emit) use ($interval, $count) {
        $i = 0;
        $future = new Future;

        $watcher = Loop::repeat($interval, function ($watcher) use (&$future, &$i) {
            Loop::disable($watcher);
            $awaitable = $future;
            $future = new Future;
            $awaitable->resolve(++$i);
        });

        try {
            while (0 === $count || $i < $count) {
                yield $emit($future);
                Loop::enable($watcher);
            }
        } finally {
            Loop::cancel($watcher);
        }
    });
}

/**
 * @param int $start
 * @param int $end
 * @param int $step
 *
 * @return \Amp\Observable
 */
function range($start, $end, $step = 1) {
    $start = (int) $start;
    $end = (int) $end;
    $step = (int) $step;

    if (0 === $step) {
        throw new \InvalidArgumentException("Step must be a non-zero integer");
    }

    if ((($end - $start) ^ $step) < 0) {
        throw new \InvalidArgumentException("Step is not of the correct sign");
    }

    return new Emitter(function (callable $emit) use ($start, $end, $step) {
        for ($i = $start; $i <= $end; $i += $step) {
            yield $emit($i);
        }
    });
}
