<?php

interface FiberScheduler
{
    /**
     * Run the scheduler.
     */
    public function run(): void;
}
