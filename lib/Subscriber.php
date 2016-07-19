<?php

namespace Amp;

/**
 * Subscriber implementation returned from implementors of \Amp\Observable.
 */
class Subscriber {
    /**
     * @var string
     */
    private $id;

    /**
     * @var callable
     */
    private $unsubscribe;

    /**
     * @param string $id
     * @param callable $unsubscribe
     */
    public function __construct($id, callable $unsubscribe) {
        $this->id = $id;
        $this->unsubscribe = $unsubscribe;
    }

    /**
     * Unsubscribes from the Observable. No future values emitted by the Observable will be received.
     */
    public function unsubscribe() {
        $unsubscribe = $this->unsubscribe;
        $unsubscribe($this->id);
    }
}
