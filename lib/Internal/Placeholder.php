<?php

namespace Amp\Internal;

use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;

/**
 * Class used by Promise implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing {@see Promise}
 *
 * @internal
 */
final class Placeholder
{
    private bool $resolved = false;

    private mixed $result;

    /** @var ResolutionQueue|null|callable(\Throwable|null, mixed): (Promise|\React\Promise\PromiseInterface|\Generator<mixed,
     *     Promise|\React\Promise\PromiseInterface|array<array-key, Promise|\React\Promise\PromiseInterface>, mixed,
     *     mixed>|null)|callable(\Throwable|null, mixed): void */
    private $onResolved;

    private ?array $resolutionTrace = null;

    /**
     * @inheritdoc
     */
    public function onResolve(callable $onResolved): void
    {
        if ($this->resolved) {
            if ($this->result instanceof Promise) {
                $this->result->onResolve($onResolved);
                return;
            }

            Loop::defer(function () use ($onResolved): void {
                /** @var mixed $result */
                $result = $onResolved(null, $this->result);

                if ($result === null) {
                    return;
                }

                if ($result instanceof \Generator) {
                    $result = new Coroutine($result);
                }

                if ($result instanceof Promise) {
                    Promise\rethrow($result);
                }
            });
            return;
        }

        if (null === $this->onResolved) {
            $this->onResolved = $onResolved;
            return;
        }

        if (!$this->onResolved instanceof ResolutionQueue) {
            /** @psalm-suppress InternalClass */
            $this->onResolved = new ResolutionQueue($this->onResolved);
        }

        /** @psalm-suppress InternalMethod */
        $this->onResolved->push($onResolved);
    }

    public function __destruct()
    {
        try {
            $this->result = null;
        } catch (\Throwable $e) {
            Loop::defer(static function () use ($e): void {
                throw $e;
            });
        }
    }

    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * @param mixed $value
     *
     * @return void
     *
     * @throws \Error Thrown if the promise has already been resolved.
     */
    public function resolve(mixed $value = null): void
    {
        if ($this->resolved) {
            $message = "Promise has already been resolved";

            if (isset($this->resolutionTrace)) {
                $trace = formatStacktrace($this->resolutionTrace);
                $message .= ". Previous resolution trace:\n\n{$trace}\n\n";
            } else {
                // @codeCoverageIgnoreStart
                $message .= ", define environment variable AMP_DEBUG or const AMP_DEBUG = true and enable assertions "
                    . "for a stacktrace of the previous resolution.";
                // @codeCoverageIgnoreEnd
            }

            throw new \Error($message);
        }

        \assert((function () {
            if (isDebugEnabled()) {
                $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
                \array_shift($trace); // remove current closure
                $this->resolutionTrace = $trace;
            }

            return true;
        })());

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

        Loop::defer(function () use ($onResolved): void {
            /** @var mixed $result */
            $result = $onResolved(null, $this->result);
            $onResolved = null; // allow garbage collection of $onResolved, to catch any exceptions from destructors

            if ($result === null) {
                return;
            }

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise) {
                Promise\rethrow($result);
            }
        });
    }

    /**
     * @param \Throwable $reason Failure reason.
     *
     * @return void
     */
    public function fail(\Throwable $reason): void
    {
        $this->resolve(new Failure($reason));
    }
}
