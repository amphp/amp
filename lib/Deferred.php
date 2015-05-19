<?php

namespace Amp;

if (defined("AMP_PRODUCTION_MODE") && AMP_PRODUCTION_MODE) {
    final class Deferred implements Promisor, Promise { use PublicPromisor; }
} else {
    final class Deferred implements Promisor { use PrivatePromisor; }
}
