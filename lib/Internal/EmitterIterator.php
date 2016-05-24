<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Coroutine;
use Amp\IncompleteException;
use Amp\ObservableIterator;

class EmitterIterator implements ObservableIterator {
    /**
     * @var \Amp\Internal\Emitted
     */
    private $emitted;
    
    /**
     * @var mixed
     */
    private $current;
    
    /**
     * @var \Amp\Internal\EmitQueue
     */
    private $queue;
    
    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;
    
    /**
     * @var bool
     */
    private $complete = false;
    
    /**
     * @param \Amp\Internal\EmitQueue $queue
     */
    public function __construct(EmitQueue $queue) {
        $this->queue = $queue;
        $this->queue->increment();
    }
    
    /**
     * Removes queue from collection.
     */
    public function __destruct() {
        if ($this->emitted !== null) {
            $this->emitted->ready();
        }
        
        $this->queue->decrement();
    }

    /**
     * {@inheritdoc}
     */
    public function isValid() {
        return new Coroutine($this->doValid());
    }
    
    /**
     * @coroutine
     *
     * @return \Generator
     *
     * @resolve bool
     *
     * @throws \Throwable|\Exception
     */
    private function doValid() {
        if ($this->awaitable !== null) {
            throw new \LogicException("Simultaneous calls to isValid() are not allowed");
        }

        try {
            $emitted = $this->queue->pull();

            if ($this->emitted !== null) {
                $this->emitted->ready();
            }

            $this->emitted = $emitted;
            $this->current = (yield $this->awaitable = $this->emitted->getAwaitable());
        } catch (\Throwable $exception) {
            $this->current = null;
            throw $exception;
        } catch (\Exception $exception) {
            $this->current = null;
            throw $exception;
        } finally {
            $this->complete = $this->queue->isComplete();
            $this->awaitable = null;
        }
        
        yield Coroutine::result(!$this->complete);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCurrent() {
        if ($this->emitted === null || $this->awaitable !== null) {
            throw new \LogicException("isValid() must be called before calling this method");
        }
        
        if ($this->complete) {
            if ($this->queue->isFailed()) {
                throw $this->queue->getReason();
            }
            
            throw new CompletedException("The observable has completed and the iterator is invalid");
        }
        
        return $this->current;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getReturn() {
        if ($this->emitted === null || $this->awaitable !== null) {
            throw new \LogicException("isValid() must be called before calling this method");
        }
        
        if (!$this->complete) {
            throw new IncompleteException("The observable has not completed");
        }
        
        if ($this->queue->isFailed()) {
            throw $this->queue->getReason();
        }
        
        return $this->current;
    }
}
