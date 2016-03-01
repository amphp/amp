<?php

namespace Interop\Async\EventLoop;

interface EventLoopInterface
{
    /**
     * @return void
     */
    public function run(callable $onStart = null);

    /**
     * @return void
     */
    public function stop();

    /**
     * @return string
     */
    public function defer(callable $callback);

    /**
     * @return string
     */
    public function delay(callable $callback, float $time);

    /**
     * @return string
     */
    public function repeat(callable $callback, float $interval);

    /**
     * @return string
     */
    public function onReadable($stream, callable $callback);

    /**
     * @return string
     */
    public function onWritable($stream, callable $callback);

    /**
     * @return string
     */
    public function onSignal(int $signo, callable $callback);

    /**
     * @return string
     */
    public function onError(callable $callback);

    /**
     * @return void
     */
    public function enable(string $watcherId);

    /**
     * @return void
     */
    public function disable(string $watcherId);

    /**
     * @return void
     */
    public function cancel(string $watcherId);
}
