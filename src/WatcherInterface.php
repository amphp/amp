<?php

namespace Interop\Async\EventLoop;

interface WatcherInterface
{
    /**
     * @return void
     */
    public function enable();

    /**
     * @return void
     */
    public function disable();

    /**
     * @return void
     */
    public function cancel();
}
