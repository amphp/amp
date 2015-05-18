<?php

namespace Amp;

class PromiseStream {
    const NOTIFY  = 0b00;
    const WAIT    = 0b01;
    const ERROR   = 0b10;

    private $promisors;
    private $index = 0;
    private $state;

    /**
     * @param \Amp\Promise $watchedPromise
     */
    public function __construct(Promise $watchedPromise) {
        $this->state = self::WAIT;
        $this->promisors[] = new PrivateFuture;
        $watchedPromise->watch(function($data) {
            $this->state = self::NOTIFY;
            $this->promisors[$this->index + 1] = new PrivateFuture;
            $this->promisors[$this->index++]->succeed($data);
        });
        $watchedPromise->when(function($error, $result) {
            if ($error) {
                $this->state = self::ERROR;
                $this->promisors[$this->index]->fail($error);
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
                case self::ERROR:
                    throw new \LogicException(
                        "Cannot advance stream: subject Promise failed"
                    );
            }
        }
    }
}
