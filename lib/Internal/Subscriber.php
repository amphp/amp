<?php

namespace Amp\Internal;

use Amp\CompletedException;
use Amp\Coroutine;
use Amp\IncompleteException;
use Amp\Observer;

final class Subscriber implements Observer {
    /**
     * @var \Amp\Internal\Subscription
     */
    private $subscription;

    /**
     * @var \Amp\Internal\Emitted
     */
    private $emitted;
    
    /**
     * @var mixed
     */
    private $current;

    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var \Throwable|\Exception|null
     */
    private $exception;

    /**
     * @param \Amp\Internal\Subscription $subscription
     */
    public function __construct(Subscription $subscription) {
        $this->subscription = $subscription;
    }
    
    /**
     * Removes queue from collection.
     */
    public function __destruct() {
        if ($this->emitted !== null) {
            $this->emitted->ready();
        }

        $this->subscription->unsubscribe();
    }

    /**
     * {@inheritdoc}
     */
    public function isValid() {
        return new Coroutine($this->valid());
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
    private function valid() {
        while ($this->awaitable !== null) {
            yield $this->awaitable; // Wait for previous calls to resolve.
        }

        if ($this->emitted !== null) {
            $this->emitted->ready();
        }

        $this->emitted = (yield $this->subscription->pull());

        try {
            $this->current = (yield $this->awaitable = $this->emitted->getAwaitable());
        } catch (\Throwable $exception) {
            $this->exception = $exception;
            throw $exception;
        } catch (\Exception $exception) {
            $this->exception = $exception;
            throw $exception;
        } finally {
            $this->complete = $this->emitted->isComplete();
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
            if ($this->exception) {
                throw $this->exception;
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
        
        if ($this->exception) {
            throw $this->exception;
        }
        
        return $this->current;
    }
}
