<?php

namespace Amp\Loop;

interface DriverControl extends \FiberScheduler
{
    /**
     * Run the event loop.
     *
     * One iteration of the loop is called one "tick". A tick covers the following steps:
     *
     *  1. Activate watchers created / enabled in the last tick / before `run()`.
     *  2. Execute all enabled defer watchers.
     *  3. Execute all due timer, pending signal and actionable stream callbacks, each only once per tick.
     *
     * The loop MUST continue to run until it is either stopped explicitly, no referenced watchers exist anymore, or an
     * exception is thrown that cannot be handled. Exceptions that cannot be handled are exceptions thrown from an
     * error handler or exceptions that would be passed to an error handler but none exists to handle them.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Stop the event loop.
     *
     * When an event loop is stopped, it continues with its current tick and exits the loop afterwards. Multiple calls
     * to stop MUST be ignored and MUST NOT raise an exception.
     *
     * @return void
     */
    public function stop(): void;
}
