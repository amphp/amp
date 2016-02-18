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
    public function tick(bool $block = true);

    /**
     * @return void
     */
    public function stop();

    /**
     * @return WatcherInterface
     */
    public function queue(callable $callback, ...$args);

    /**
     * @return WatcherInterface
     */
    public function once(callable $callback, float $time, $data = null);

    /**
     * @return WatcherInterface
     */
    public function repeat(callable $callback, float $interval, $data = null);

    /**
     * @return WatcherInterface
     */
    public function onReadable($stream, callable $callback, $data = null);

    /**
     * @return WatcherInterface
     */
    public function onWritable($stream, callable $callback, $data = null);

    /**
     * @return WatcherInterface
     */
    public function onSignal(int $signo, callable $callback, $data = null);

    /**
     * @return WatcherInterface
     */
    public function onError(callable $callback);
}
