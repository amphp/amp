<?php

interface Awaitable
{
    /**
     * Register a callback to be invoked when the awaitable is resolved.
     *
     * @param callable(?Throwable $exception, mixed $value):void $onResolve
     */
    public function onResolve(callable $onResolve): void;
}
