<?php

namespace Amp;

// @codeCoverageIgnoreStart
try {
    if (@assert(false)) {
        // PHP7 production environment (zend.assertions=0, assert.exception=0)
        eval("namespace Amp; final class Deferred implements Promisor, Promise { use PublicPromisor; }");
    } else {
        // PHP < 7 or dev environment (zend.assertions=1, assert.exception=0)
        final class Deferred implements Promisor { use PrivatePromisor; }
    }
} catch (\AssertionError $e) {
    // PHP7 dev environment (zend.assertions=1, assert.exception=1)
    eval("namespace Amp; final class Deferred implements Promisor { use PrivatePromisor; }");
}
// @codeCoverageIgnoreEnd