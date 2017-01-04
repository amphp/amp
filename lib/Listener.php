<?php

namespace Amp;

use Interop\Async\Promise;

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
    private $deferreds = [];

    /** @var int */
    private $position = -1;

    /** @var \Amp\Deferred|null */
    private $deferred;

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

        $deferred = &$this->deferred;
        $values = &$this->values;
        $deferreds = &$this->deferreds;
        $resolved = &$this->resolved;

        $this->stream->listen(static function ($value) use (&$deferred, &$values, &$deferreds, &$resolved) {
            $values[] = $value;
            $deferreds[] = $pressure = new Deferred;

            if ($deferred !== null) {
                $temp = $deferred;
                $deferred = null;
                $temp->resolve(true);
            }

            if ($resolved) {
                return null;
            }

            return $pressure->promise();
        });

        $result = &$this->result;
        $error = &$this->exception;

        $this->stream->when(static function ($exception, $value) use (&$deferred, &$result, &$error, &$resolved) {
            $resolved = true;

            if ($exception) {
                $result = null;
                $error = $exception;
                if ($deferred !== null) {
                    $deferred->fail($exception);
                }
                return;
            }

            $result = $value;
            if ($deferred !== null) {
                $deferred->resolve(false);
            }
        });
    }

    /**
     * Marks the listener as resolved to relieve back-pressure on the stream.
     */
    public function __destruct() {
        $this->resolved = true;

        foreach ($this->deferreds as $deferred) {
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
     * @return \Interop\Async\Promise<bool>
     */
    public function advance(): Promise {
        if (isset($this->deferreds[$this->position])) {
            $future = $this->deferreds[$this->position];
            unset($this->values[$this->position], $this->deferreds[$this->position]);
            $future->resolve();
        }

        ++$this->position;

        if (\array_key_exists($this->position, $this->values)) {
            return new Success(true);
        }

        if ($this->resolved) {
            --$this->position;

            if ($this->exception) {
                return new Failure($this->exception);
            }

            return new Success(false);
        }

        $this->deferred = new Deferred;
        return $this->deferred->promise();
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

        $values = $this->values;
        $this->values = [];
        $this->position = -1;

        $deferreds = $this->deferreds;
        $this->deferreds = [];
        foreach ($deferreds as $deferred) {
            $deferred->resolve();
        }

        return $values;
    }
}
