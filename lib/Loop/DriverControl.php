<?php

namespace Amp\Loop;

interface DriverControl
{
    /**
     * Run the driver event loop.
     *
     * @return void
     *
     * @see Driver::run()
     */
    public function run();

    /**
     * Stop the driver event loop.
     *
     * @return void
     *
     * @see Driver::stop()
     */
    public function stop();
}
