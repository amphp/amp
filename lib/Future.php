<?php

namespace Amp;

if (!defined("AMP_DEBUG") || \AMP_DEBUG) {
    final class Future implements Promisor { use PrivatePromisor; }
} else {
    final class Future implements Promisor, Promise { use PublicPromisor; }
}
