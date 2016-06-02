<?php

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Disposable implementation returned from implementors of \Amp\Observable.
 */
final class Subscriber implements Awaitable {
    /**
     * @var string
     */
    private $id;

    /**
     * @var \Interop\Async\Awaitable
     */
    private $awaitable;

    /**
     * @var callable
     */
    private $unsubscribe;

    /**
     * @param string $id
     * @param \Interop\Async\Awaitable $awaitable
     * @param callable $unsubscribe
     */
    public function __construct($id, Awaitable $awaitable, callable $unsubscribe) {
        $this->id = $id;
        $this->awaitable = $awaitable;
        $this->unsubscribe = $unsubscribe;
    }

    /**
     * {@inheritdoc}
     */
    public function when(callable $onResolved) {
        $this->awaitable->when($onResolved);
    }

    /**
     * {@inheritdoc}
     */
    public function unsubscribe() {
        $unsubscribe = $this->unsubscribe;
        $unsubscribe($this->id);
    }
}
