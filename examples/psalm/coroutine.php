<?php

use function Amp\coroutine;
use function Amp\Promise\wait;

/**
 * @return void
 */
function accept(string $param)
{

}

$coroutine = coroutine(function (): \Generator {
    yield;

    return 123;
});

// psalm-expect InvalidScalarArgument
accept(wait($coroutine()));


