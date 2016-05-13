<?php

namespace Interop\Async\EventLoop;

interface LoopDriverFactory
{
	/**
	 * Create a new event loop driver instance.
	 *
	 * @return LoopDriver
	 */
	public function create();
}
