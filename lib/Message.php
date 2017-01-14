<?php

namespace Amp;

use AsyncInterop\Promise;

/**
 * Creates a buffered message from a stream. The message can be consumed in chunks using the advance() and getCurrent()
 * methods or it may be buffered and accessed in its entirety by waiting for the promise to resolve.
 */
class Message extends Listener implements Promise {
    use Internal\Placeholder;

    /**
     * @param \Amp\Stream $stream Stream that only emits strings.
     */
    public function __construct(Stream $stream) {
        parent::__construct($stream);

        $stream->when(function($e) {
            if ($e) {
                $this->fail($e);
                return;
            }

            $this->resolve(\implode($this->getBuffered()));
        });
    }
}
