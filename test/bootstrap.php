<?php

namespace Amp\Test;

require __DIR__ . "/../vendor/autoload.php";

error_reporting(E_ALL);

class PromisorPrivateImpl implements \Amp\Promisor {
    use \Amp\PrivatePromisor;
}
class PromisorPublicImpl implements \Amp\Promisor, \Amp\Promise {
    use \Amp\PublicPromisor;
}
class StructTestFixture {
    use \Amp\Struct;
    public $callback;
}
