<?php

namespace Amp;

use AsyncInterop\Promise;

/**
 * Creates a buffered message from a Stream. The message can be consumed in chunks using the advance() and getCurrent()
 * methods or it may be buffered and accessed in its entirety by waiting for the promise to resolve.
 *
 * Buffering Example:
 *
 * $message = new Message($stream); // $stream is an instance of \Amp\Stream emitting only strings.
 * $content = yield $message;
 *
 * Streaming Example:
 *
 * $message = new Message($stream); // $stream is a Stream emitting only strings.
 *
 * while (yield $message->advance()) {
 *     $chunk = $message->getCurrent();
 *     // Immediately use $chunk, reducing memory consumption since the entire message is never buffered.
 * }
 */
class Message implements Iterator, Promise {
    use Internal\Placeholder;

    const LISTENING = 0;
    const BUFFERING = 1;
    const WAITING   = 2;
    const COMPLETE  = 4;

    /** @var \Amp\Listener|null */
    private $listener;

    /** @var int */
    private $status = self::LISTENING;

    /** @var mixed Final result of the stream. */
    private $value;

    /**
     * @param \Amp\Stream $stream Stream that only emits strings.
     */
    public function __construct(Stream $stream) {
        $this->listener = new Listener($stream);

        $stream->when(function($exception, $value) {
            if ($exception) {
                $this->fail($exception);
                return;
            }

            $result = \implode($this->listener->drain());
            $this->listener = null;
            $this->status = \strlen($result) ? self::BUFFERING : self::WAITING;
            $this->value = $value;
            $this->resolve($result);
        });
    }

    /**
     * Returns a promise that resolves with true when more data in the message is available or false if the message is
     * complete.
     *
     * @return \AsyncInterop\Promise<bool>
     *
     * @throws \Error If the message has resolved.
     */
    public function advance(): Promise {
        if ($this->listener) {
            return $this->listener->advance();
        }

        switch ($this->status) {
            case self::BUFFERING:
                $this->status = self::WAITING;
                return new Success(true);

            case self::WAITING:
                $this->status = self::COMPLETE;
                return new Success(false);

            default:
                throw new \Error("The stream has resolved");
        }
    }

    /**
     * @return string Current chunk of the message.
     *
     * @throws \Error If the message has resolved.
     */
    public function getCurrent(): string {
        if ($this->listener) {
            return $this->listener->getCurrent();
        }

        switch ($this->status) {
            case self::COMPLETE:
                throw new \Error("The stream has resolved");

            default:
                return $this->result;
        }
    }

    /**
     * @return mixed Result of the Stream (may not be a string).
     *
     * @throws \Error If the message has not resolved.
     */
    public function getResult() {
        if ($this->listener) {
            return $this->listener->getResult();
        }

        return $this->value;
    }
}
