<?php

namespace Amp\Awaitable\Internal;

use Amp\Awaitable\Failure;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

trait Placeholder {
    /**
     * @var bool
     */
    private $resolved = false;

    /**
     * @var mixed
     */
    private $result;
    
    /**
     * @var callable|\Amp\Awaitable\Internal\WhenQueue|null
     */
    private $onResolved;
    
    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        if ($this->resolved) {
            if ($this->result instanceof Awaitable) {
                $this->result->when($onResolved);
                return;
            }

            try {
                $onResolved(null, $this->result);
            } catch (\Throwable $exception) {
                Loop::defer(static function () use ($exception) {
                    throw $exception;
                });
            } catch (\Exception $exception) {
                Loop::defer(static function () use ($exception) {
                    throw $exception;
                });
            }
            return;
        }

        if (null === $this->onResolved) {
            $this->onResolved = $onResolved;
        } elseif (!$this->onResolved instanceof WhenQueue) {
            $this->onResolved = new WhenQueue($this->onResolved);
            $this->onResolved->push($onResolved);
        } else {
            $this->onResolved->push($onResolved);
        }
    }

    /**
     * @param mixed $value
     */
    protected function resolve($value = null) {
        if ($this->resolved) {
            throw new \LogicException("Awaitable has already been resolved");
        }

        $this->resolved = true;

        if ($value instanceof Awaitable) {
            if ($this === $value) {
                throw new \InvalidArgumentException("Cannot resolve an awaitable with itself");
            }

            $this->result = $value;

            if ($this->onResolved !== null) {
                $this->result->when($this->onResolved);
            }
        } else {
            $this->result = $value;

            if ($this->onResolved !== null) {
                try {
                    $onResolved = $this->onResolved;
                    $onResolved(null, $this->result);
                } catch (\Throwable $exception) {
                    Loop::defer(static function () use ($exception) {
                        throw $exception;
                    });
                } catch (\Exception $exception) {
                    Loop::defer(static function () use ($exception) {
                        throw $exception;
                    });
                }
            }
        }

        $this->onResolved = null;
    }

    /**
     * @param \Throwable|\Exception $reason
     */
    protected function fail($reason) {
        $this->resolve(new Failure($reason));
    }
}
