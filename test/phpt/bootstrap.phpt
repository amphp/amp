--TEST--
Bootstrap file sets factory
--FILE--
<?php

require __DIR__ . "/../../vendor/autoload.php";
require __DIR__ . "/../../lib/bootstrap.php";

AsyncInterop\Loop::execute(function () {
	print AsyncInterop\Loop::get() instanceof Amp\Loop\Loop ? "ok" : "fail";
});

?>
--EXPECT--
ok
