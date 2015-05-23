<?php

namespace Amp;

class PromiseStream implements Streamable {
    private $promisors;
    private $index = 0;

    /**
     * @param \Amp\Promise $watchedPromise
     */
    public function __construct(Promise $watchedPromise) {
        $this->promisors[] = new Deferred;
        $watchedPromise->watch(function($data) {
            $this->promisors[$this->index + 1] = new Deferred;
            $this->promisors[$this->index++]->succeed($data);
        });
        $watchedPromise->when(function($error, $result) {
            if ($error) {
                $this->promisors[$this->index]->fail($error);
            } else {
                $this->promisors[$this->index]->succeed();
            }
        });
    }

    /**
     * Generate a stream of promises that may be iteratively yielded to await resolution
     *
     * NOTE: Only values sent to Promise::update() will be streamed. The final resolution
     * value of the promise is not sent to the stream. If the Promise is failed that failure
     * will resolve the stream's current Promise instance.
     *
     * @throws \LogicException if stream is in an un-iterable state
     * @return \Generator
     */
    public function stream() {
        while ($this->promisors) {
            $key = key($this->promisors);
            yield $this->promisors[$key]->promise();
            unset($this->promisors[$key]);
        }
    }

    /**
     * Buffer all remaining promise placeholders in the stream
     *
     * @return \Generator
     */
    public function buffer() {
        $buffer = [];
        foreach ($this->stream() as $promise) {
            $buffer[] = (yield $promise);
        }
        array_pop($buffer);

        yield "return" => $buffer;
    }
}
