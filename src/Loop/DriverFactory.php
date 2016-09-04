<?php

namespace Interop\Async\Loop;

/**
 * Allows creating new driver instances.
 *
 * @see \Interop\Async\Loop::setFactory()
 */
interface DriverFactory
{
	/**
	 * Create a new event loop driver instance.
	 *
	 * @return Driver
	 */
	public function create();
}
