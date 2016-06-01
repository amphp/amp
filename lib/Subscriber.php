<?php

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Disposable implementation returned from implementors of \Amp\Observable.
 */
final class Subscriber implements Disposable {
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
    private $dispose;

    /**
     * @param string $id
     * @param \Interop\Async\Awaitable $awaitable
     * @param callable $dispose
     */
    public function __construct($id, Awaitable $awaitable, callable $dispose) {
        $this->id = $id;
        $this->awaitable = $awaitable;
        $this->dispose = $dispose;
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
    public function dispose() {
        $dispose = $this->dispose;
        $dispose($this->id);
    }
}
