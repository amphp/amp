<?php

namespace Amp\Test;

require __DIR__ . "/../vendor/autoload.php";

class PromisorPrivateImpl implements \Amp\Promisor {
    use \Amp\PrivatePromisor;
}
class PromisorPublicImpl implements \Amp\Promisor, \Amp\Promise {
    use \Amp\PublicPromisor;
}
