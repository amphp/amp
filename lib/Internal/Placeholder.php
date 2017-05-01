<?php

namespace Amp\Internal;

use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Trait used by Promise implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Promise.
 *
 * @internal
 */
trait Placeholder {
    /** @var bool */
    private $resolved = false;

    /** @var mixed */
    private $result;

    /** @var callable|\Amp\Internal\ResolutionQueue|null */
    private $onResolved;

    /** @var null|array */
    private $resolutionTrace;

    /**
     * @inheritdoc
     */
    public function onResolve(callable $onResolved) {
        if ($this->resolved) {
            if ($this->result instanceof Promise) {
                $this->result->onResolve($onResolved);
                return;
            }

            try {
                $result = $onResolved(null, $this->result);

                if ($result === null) {
                    return;
                }

                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }

                if ($result instanceof Promise || $result instanceof ReactPromise) {
                    Promise\rethrow($result);
                }
            } catch (\Throwable $exception) {
                Loop::defer(function () use ($exception) {
                    throw $exception;
                });
            }
            return;
        }

        if (null === $this->onResolved) {
            $this->onResolved = $onResolved;
            return;
        }

        if (!$this->onResolved instanceof ResolutionQueue) {
            $this->onResolved = new ResolutionQueue($this->onResolved);
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
            $message = "Promise has already been resolved";

            if (isset($this->resolutionTrace)) {
                $trace = formatStacktrace($this->resolutionTrace);
                $message .= ". Previous resolution trace:\n\n{$trace}\n\n";
            } else {
                $message .= ", define const AMP_DEBUG = true and enable assertions for a stacktrace of the previous resolution.";
            }

            throw new \Error($message);
        }

        \assert((function () {
            if (\defined("AMP_DEBUG") && \AMP_DEBUG) {
                $trace = \debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                \array_shift($trace); // remove current closure
                $this->resolutionTrace = $trace;
            }

            return true;
        })());

        if ($value instanceof ReactPromise) {
            $value = Promise\adapt($value);
        }

        $this->resolved = true;
        $this->result = $value;

        if ($this->onResolved === null) {
            return;
        }

        $onResolved = $this->onResolved;
        $this->onResolved = null;

        if ($this->result instanceof Promise) {
            $this->result->onResolve($onResolved);
            return;
        }

        try {
            $result = $onResolved(null, $this->result);

            if ($result === null) {
                return;
            }

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise || $result instanceof ReactPromise) {
                Promise\rethrow($result);
            }
        } catch (\Throwable $exception) {
            Loop::defer(function () use ($exception) {
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
