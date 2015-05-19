<?php

namespace Amp;

if (!defined("AMP_DEBUG") || \AMP_DEBUG) {
    final class Deferred implements Promisor { use PrivatePromisor; }
} else {
    final class Deferred implements Promisor, Promise { use PublicPromisor; }
}
