--TEST--
Test order of destruction not interfering with access to UV handles
--SKIPIF--
<?php
\extension_loaded("uv") or die("SKIP: ext/uv required for this test");
?>
--FILE--
<?php

include __DIR__.'/../../vendor/autoload.php';

use Amp\Loop;

Loop::setState('test', new class {
    private $handle;
    public function __construct()
    {
        $this->handle = Loop::repeat(10, function () {});
    }
    public function __destruct()
    {
        Loop::cancel($this->handle);
        print "ok";
    }
});

Loop::delay(0, [Loop::class, "stop"]);

?>
--EXPECT--
ok
