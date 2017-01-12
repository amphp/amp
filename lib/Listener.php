<?php

namespace Amp;

use AsyncInterop\Promise;

/**
 * Asynchronous iterator that can be used within a coroutine to iterate over the emitted values from an Stream.
 *
 * Example:
 * $listener = new Listener($stream); // $stream is an instance of \Amp\Stream
 * while (yield $listener->advance()) {
 *     $emitted = $listener->getCurrent();
 * }
 * $result = $listener->getResult();
 */
class Listener {
    /** @var \Amp\Stream */
    private $stream;

    /** @var mixed[] */
    private $values = [];

    /** @var \Amp\Deferred[] */
    private $backPressure = [];

    /** @var int */
    private $position = -1;

    /** @var \Amp\Deferred|null */
    private $waiting;

    /** @var bool */
    private $resolved = false;

    /** @var mixed */
    private $result;

    /** @var \Throwable|null */
    private $exception;

    /**
     * @param \Amp\Stream $stream
     */
    public function __construct(Stream $stream) {
        $this->stream = $stream;

        $waiting = &$this->waiting;
        $values = &$this->values;
        $backPressure = &$this->backPressure;
        $resolved = &$this->resolved;

        $this->stream->listen(static function ($value) use (&$waiting, &$values, &$backPressure, &$resolved) {
            $values[] = $value;
            $backPressure[] = $pressure = new Deferred;

            if ($waiting !== null) {
                $deferred = $waiting;
                $waiting = null;
                $deferred->resolve(true);
            }

            if ($resolved) {
                return null;
            }

            return $pressure->promise();
        });

        $result = &$this->result;
        $error = &$this->exception;

        $this->stream->when(static function ($exception, $value) use (&$waiting, &$result, &$error, &$resolved) {
            $resolved = true;

            if ($exception) {
                $result = null;
                $error = $exception;
                if ($waiting !== null) {
                    $waiting->fail($exception);
                }
                return;
            }

            $result = $value;
            if ($waiting !== null) {
                $waiting->resolve(false);
            }
        });
    }

    /**
     * Marks the listener as resolved to relieve back-pressure on the stream.
     */
    public function __destruct() {
        $this->resolved = true;

        foreach ($this->backPressure as $deferred) {
            $deferred->resolve();
        }
    }

    /**
     * @return \Amp\Stream The stream being used by the listener.
     */
    public function stream(): Stream {
        return $this->stream;
    }

    /**
     * Succeeds with true if an emitted value is available by calling getCurrent() or false if the stream has
     * resolved. If the stream fails, the returned promise will fail with the same exception.
     *
     * @return \AsyncInterop\Promise<bool>
     *
     * @throws \Error If the prior promise returned from this method has not resolved.
     */
    public function advance(): Promise {
        if ($this->waiting !== null) {
            throw new \Error("The prior promise returned must resolve before invoking this method again");
        }

        if (isset($this->backPressure[$this->position])) {
            $future = $this->backPressure[$this->position];
            unset($this->values[$this->position], $this->backPressure[$this->position]);
            $future->resolve();
        }

        ++$this->position;

        if (\array_key_exists($this->position, $this->values)) {
            return new Success(true);
        }

        if ($this->resolved) {
            if ($this->exception) {
                return new Failure($this->exception);
            }

            return new Success(false);
        }

        $this->waiting = new Deferred;
        return $this->waiting->promise();
    }

    /**
     * Gets the last emitted value or throws an exception if the stream has completed.
     *
     * @return mixed Value emitted from stream.
     *
     * @throws \Error If the stream has resolved or advance() was not called before calling this method.
     */
    public function getCurrent() {
        if (empty($this->values) && $this->resolved) {
            throw new \Error("The stream has resolved");
        }

        if (!\array_key_exists($this->position, $this->values)) {
            throw new \Error("Promise returned from advance() must resolve before calling this method");
        }

        return $this->values[$this->position];
    }

    /**
     * Gets the result of the stream or throws the failure reason. Also throws an exception if the stream has
     * not completed.
     *
     * @return mixed Final return value of the stream.
     *
     * @throws \Error If the stream has not completed.
     * @throws \Throwable The exception used to fail the stream.
     */
    public function getResult() {
        if (!$this->resolved) {
            throw new \Error("The stream has not resolved");
        }

        if ($this->exception) {
            throw $this->exception;
        }

        return $this->result;
    }

    /**
     * Returns an array of values that were not consumed by the listener before the Stream completed.
     *
     * @return array Unconsumed emitted values.
     *
     * @throws \Error If the stream has not completed.
     */
    public function drain(): array {
        if (!$this->resolved) {
            throw new \Error("The stream has not resolved");
        }

        unset($this->values[$this->position]);

        $values = $this->values;
        $this->values = [];

        $deferreds = $this->backPressure;
        $this->backPressure = [];
        foreach ($deferreds as $deferred) {
            $deferred->resolve();
        }

        return $values;
    }
}
