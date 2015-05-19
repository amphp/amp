<?php

namespace Amp;

class PromiseStream implements Streamable {
    const NOTIFY = 0b000;
    const WAIT   = 0b001;
    const ERROR  = 0b010;
    const DONE   = 0b100;

    private $promisors;
    private $index = 0;
    private $state;

    /**
     * @param \Amp\Promise $watchedPromise
     */
    public function __construct(Promise $watchedPromise) {
        $this->state = self::WAIT;
        $this->promisors[] = new Deferred;
        $watchedPromise->watch(function($data) {
            $this->state = self::NOTIFY;
            $this->promisors[$this->index + 1] = new Deferred;
            $this->promisors[$this->index++]->succeed($data);
        });
        $watchedPromise->when(function($error, $result) {
            if ($error) {
                $this->state = self::ERROR;
                $this->promisors[$this->index]->fail($error);
            } else {
                $this->state = self::DONE;
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
    public function stream(): \Generator {
        while ($this->promisors) {
            $key = key($this->promisors);
            yield $this->promisors[$key]->promise();
            switch ($this->state) {
                case self::NOTIFY:
                    $this->state = self::WAIT;
                    unset($this->promisors[$key]);
                    break;
                case self::WAIT:
                    throw new \LogicException(
                        "Cannot advance stream: previous Promise not yet resolved"
                    );
                    break;
                case self::DONE:
                    return;
                case self::ERROR:
                    throw new \LogicException(
                        "Cannot advance stream: subject Promise failed"
                    );
            }
        }
    }

    /**
     * Buffer all remaining promise placeholders in the stream
     *
     * @return \Generator
     */
    public function buffer(): \Generator {
        $buffer = [];
        foreach ($this->stream() as $promise) {
            $buffer[] = yield $promise;
        }
        array_pop($buffer);

        return $buffer;
    }
}
