<?php

namespace Amp;

/**
 * Async value producers can use PromiseStream instances to simplify
 * streaming promise updates inside coroutines by wrapping Promise
 * instances in PromiseStream objects. Instead of promise consumers
 * needing to use Promise::when() callbacks to access updates they
 * can instead use the PromiseStream API as part of a coroutine to
 * consume promise updates as they arrive as shown here:
 *
 *  while (yield $stream->valid()) {
 *      $update = $stream->consume();
 *  }
 */
class PromiseStream {
    private $readPos = 0;
    private $writePos = 0;
    private $valids = [];
    private $values = [];

    public function __construct(Promise $promise) {
        $this->valids[$this->readPos] = new Deferred;
        $promise->watch(function ($data) {
            $curPos = $this->writePos;
            $this->writePos++;
            $this->valids[] = new Deferred;
            $this->values[$curPos] = $data;
            $this->valids[$curPos]->succeed(true);
        });
        $promise->when(function ($error, $result) {
            if ($error) {
                $this->valids[$this->writePos]->fail($error);
            } else {
                $curPos = $this->writePos;
                $this->values[$this->writePos++] = $result;
                $this->valids[$curPos]->succeed(false);
            }
        });
    }

    /**
     * Will more data arrive on the stream?
     *
     * Stream consumers should await the result of the valid() promise before
     * consuming stream output.
     *
     * @return \Amp\Promise
     */
    public function valid() {
        return $this->valids
            ? $this->valids[$this->readPos]->promise()
            : new Success(false)
        ;
    }

    /**
     * Consume buffered stream data
     *
     * @throws \LogicException upon attempting to consume unresolved data
     * @return mixed Returns async updates passed to the underlying promise
     */
    public function consume() {
        if ($this->writePos > $this->readPos) {
            $curPos = $this->readPos;
            $this->readPos++;
            $result = $this->values[$curPos];
            unset(
                $this->valids[$curPos],
                $this->values[$curPos]
            );
            return $result;
        } else  {
            $word = $this->valids ? "unresolved" : "completed";
            throw new \LogicException(
                "Cannot advance PromiseStream beyond {$word} index {$this->readPos}"
            );
        }
    }
}
