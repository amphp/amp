--TEST--
Bootstrap file sets factory
--FILE--
<?php

require __DIR__ . "/../../vendor/autoload.php";
require __DIR__ . "/../../lib/bootstrap.php";

Loop::execute(function () {
	print Loop::get() instanceof Amp\Loop\Loop ? "ok";
});

?>
--EXPECT--
ok
