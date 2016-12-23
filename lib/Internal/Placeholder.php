<?php declare(strict_types = 1);

namespace Amp\Internal;

use Amp\Failure;
use Interop\Async\Loop;
use Interop\Async\Promise;

/**
 * Trait used by Promise implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Interop\Async\Promise.
 *
 * @internal
 */
trait Placeholder {
    /** @var bool */
    private $resolved = false;

    /** @var mixed */
    private $result;

    /** @var callable|\Amp\Internal\WhenQueue|null */
    private $onResolved;

    /**
     * @inheritdoc
     */
    public function when(callable $onResolved) {
        if ($this->resolved) {
            if ($this->result instanceof Promise) {
                $this->result->when($onResolved);
                return;
            }

            try {
                $onResolved(null, $this->result);
            } catch (\Throwable $exception) {
                Loop::defer(static function () use ($exception) {
                    throw $exception;
                });
            }
            return;
        }

        if (null === $this->onResolved) {
            $this->onResolved = $onResolved;
            return;
        }

        if (!$this->onResolved instanceof WhenQueue) {
            $this->onResolved = new WhenQueue($this->onResolved);
        }

        $this->onResolved->push($onResolved);
    }

    /**
     * @param mixed $value
     *
     * @throws \Error Thrown if the promise has already been resolved.
     */
    private function resolve($value = null) {
        if ($this->resolved) {
            throw new \Error("Promise has already been resolved");
        }

        $this->resolved = true;
        $this->result = $value;

        if ($this->onResolved === null) {
            return;
        }

        $onResolved = $this->onResolved;
        $this->onResolved = null;

        if ($this->result instanceof Promise) {
            $this->result->when($onResolved);
            return;
        }

        try {
            $onResolved(null, $this->result);
        } catch (\Throwable $exception) {
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        }
    }

    /**
     * @param \Throwable $reason Failure reason.
     */
    private function fail(\Throwable $reason) {
        $this->resolve(new Failure($reason));
    }
}
