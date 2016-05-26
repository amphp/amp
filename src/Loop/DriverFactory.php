<?php

namespace Interop\Async\Loop;

interface DriverFactory
{
	/**
	 * Create a new event loop driver instance.
	 *
	 * @return Driver
	 */
	public function create();
}
