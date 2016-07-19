<?php

namespace Amp;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): mixed $onNext
 * @param callable(mixed $value): mixed|null $onComplete
 *
 * @return \Amp\Observable
 */
function each(Observable $observable, callable $onNext, callable $onComplete = null) {
    return new Emitter(function (callable $emit) use ($observable, $onNext, $onComplete) {
        $result = (yield $observable->subscribe(function ($value) use ($emit, $onNext) {
            return $emit($onNext($value));
        }));

        if ($onComplete === null) {
            yield Coroutine::result($result);
            return;
        }

        yield Coroutine::result($onComplete($result));
    });
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): bool $filter
 *
 * @return \Amp\Observable
 */
function filter(Observable $observable, callable $filter) {
    return new Emitter(function (callable $emit) use ($observable, $filter) {
        yield Coroutine::result(yield $observable->subscribe(function ($value) use ($emit, $filter) {
            if (!$filter($value)) {
                return null;
            }
            return $emit($value);
        }));
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
        $subscriptions = [];

        foreach ($observables as $observable) {
            $subscriptions[] = $observable->subscribe($emit);
        }

        try {
            $result = (yield all($subscriptions));
        } finally {
            foreach ($subscriptions as $subscription) {
                $subscription->unsubscribe();
            }
        }

        yield Coroutine::result($result);
    });
}


/**
 * Creates an observable from the given array of observables, emitting the success value of each provided awaitable or
 * failing if any awaitable fails.
 *
 * @param \Interop\Async\Awaitable[] $awaitables
 *
 * @return \Amp\Observable
 */
function stream(array $awaitables) {
    $postponed = new Postponed;

    if (empty($awaitables)) {
        $postponed->complete();
        return $postponed;
    }

    $pending = \count($awaitables);
    $onResolved = function ($exception, $value) use (&$pending, $postponed) {
        if ($pending <= 0) {
            return;
        }

        if ($exception) {
            $pending = 0;
            $postponed->fail($exception);
            return;
        }

        $postponed->emit($value);

        if (--$pending === 0) {
            $postponed->complete();
        }
    };

    foreach ($awaitables as $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when($onResolved);
    }

    return $postponed;
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

    $postponed = new Postponed;

    Loop::repeat($interval, function ($watcher) use (&$i, $postponed, $count) {
        $postponed->emit(++$i);

        if ($i === $count) {
            Loop::cancel($watcher);
            $postponed->resolve();
        }
    });

    return $postponed->getObservable();
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
