<?php

class ReflectionFiberScheduler extends ReflectionFiber
{
    /**
     * @param FiberScheduler $scheduler
     */
    public function __construct(FiberScheduler $scheduler) { }

    /**
     * @return FiberScheduler The instance used to create the fiber.
     */
    public function getScheduler(): FiberScheduler { }
}
