<?php

namespace Amp;

/**
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 * 
 * @TODO remove this and use an anonymous class once PHP7 is required
 */
class PrivatePlaceholder implements Promise {
    use Placeholder;
}
