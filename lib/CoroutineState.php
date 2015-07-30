<?php

namespace Amp;

/**
 * This class only exists for legacy PHP5; applications should not treat
 * it as part of the public API.
 *
 * @TODO Remove in favor of an anonymous class once PHP 5 is no longer supported
 */
class CoroutineState {
    use Struct;
    public $reactor;
    public $promisor;
    public $generator;
    public $returnValue;
    public $currentPromise;
    public $nestingLevel;
}
