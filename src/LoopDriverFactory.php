<?php

namespace Interop\Async;

interface LoopDriverFactory
{
	/**
	 * Create a new event loop driver instance.
	 *
	 * @return LoopDriver
	 */
	public function create();
}
