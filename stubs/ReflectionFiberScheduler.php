<?php

class ReflectionFiberScheduler extends ReflectionFiber
{
    /**
     * @param FiberScheduler $scheduler
     *
     * @throws FiberError If the {@see FiberScheduler} has not been used to suspend a fiber.
     */
    public function __construct(FiberScheduler $scheduler) { }

    /**
     * @return FiberScheduler The instance used to create the fiber.
     */
    public function getFiberScheduler(): FiberScheduler { }
}
