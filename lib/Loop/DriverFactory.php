<?php

namespace AsyncInterop\Loop;

/**
 * Allows creating new driver instances.
 *
 * @see \AsyncInterop\Loop::setFactory()
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
