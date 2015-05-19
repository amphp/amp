<?php

namespace Amp;

/**
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 */
class PrivatePlaceholder implements Promise {
    use Placeholder;
}
