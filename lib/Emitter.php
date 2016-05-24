<?php

namespace Amp;

use Interop\Async\Loop;

class Emitter implements Observable {
    /**
     * @var callable|null
     */
    private $emitter;
    
    /**
     * @var \Amp\Internal\EmitQueue
     */
    private $queue;

    /**
     * @param callable $emitter
     */
    public function __construct(callable $emitter) {
        $this->emitter = $emitter;
        $this->queue = new Internal\EmitQueue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function dispose() {
        $this->emitter = null;
        $this->queue->dispose();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getIterator() {
        if ($this->emitter !== null) {
            $emitter = $this->emitter;
            $this->emitter = null;

            // Asynchronously start the emitter.
            Loop::defer(function () use ($emitter) {
                $this->queue->start($emitter);
            });
        }

        return new Internal\EmitterIterator($this->queue);
    }
}
