<?php

require __DIR__ . '/../../vendor/autoload.php';

use function Amp\call;
use function Amp\delay;
use function Amp\Promise\wait;

/**
 * @return void
 */
function accept(string $param)
{
}

$coroutine = call(function (): \Generator {
    yield delay(10);

    return 123;
});

// psalm-expect InvalidScalarArgument
accept(wait($coroutine));

// psalm-expect InvalidArgument
accept(call(function (): \Generator {
    yield delay(10);

    return 'foobar';
}));

// psalm-expect InvalidScalarArgument
accept(wait(call(function (): int {
    return 42;
})));

// wait unwraps the promise, so this is accepted
accept(wait(call(function (): \Generator {
    yield delay(10);

    return 'foobar';
})));

// wait unwraps the promise, so this is accepted
accept(wait(call(function (): string {
    return 'foobar';
})));
