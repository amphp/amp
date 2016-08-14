<?php

namespace Amp;

use Interop\Async\Loop;

final class Emitter implements Observable {
    use Internal\Producer;

    /**
     * @param callable(callable $emit): \Generator $emitter
     */
    public function __construct(callable $emitter) {
        $this->init();

		// defer first emit until next tick in order to give *all* subscribers a chance to subscribe first
		$pending = true;
		Loop::defer(static function() use (&$pending) {
			if ($pending instanceof Deferred) {
				$pending->resolve();
			}
			$pending = false;
		});
		$emit = function ($value) use (&$pending) {
			if ($pending) {
				if ($pending === true) {
					$pending = new Deferred;
				}
				$pending->when(function() use ($value) {
					$this->emit($value);
				});
				return $pending->getAwaitable();
			}

			return $this->emit($value);
		};

        $result = $emitter($emit);

        if (!$result instanceof \Generator) {
            throw new \Error("The callable did not return a Generator");
        }

        $coroutine = new Coroutine($result);
        $coroutine->when(function ($exception, $value) {
            if ($exception) {
                $this->fail($exception);
                return;
            }

            $this->resolve($value);
        });
    }
}
