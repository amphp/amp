<?php

namespace Amp\Loop;

interface DriverControl extends \FiberScheduler
{
    /**
     * Run the driver event loop.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Stop the driver event loop.
     *
     * @return void
     */
    public function stop(): void;
}
