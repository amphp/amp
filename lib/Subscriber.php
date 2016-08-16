<?php

declare(strict_types=1);

namespace Amp;

/**
 * Subscriber implementation returned from implementors of \Amp\Observable.
 */
class Subscriber {
    private $id;
    
    /** @var callable|null */
    private $unsubscribe;

    /**
     * @param mixed $id
     * @param callable $unsubscribe
     */
    public function __construct($id, callable $unsubscribe = null) {
        $this->id = $id;
        $this->unsubscribe = $unsubscribe;
    }

    /**
     * Unsubscribes from the Observable. No future values emitted by the Observable will be received.
     */
    public function unsubscribe() {
        if ($this->unsubscribe) {
            ($this->unsubscribe)($this->id);
        }
    }
}
