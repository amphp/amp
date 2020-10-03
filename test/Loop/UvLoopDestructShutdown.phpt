--TEST--
Test order of destruction not interfering with access to UV handles
--SKIPIF--
<?php
\extension_loaded("uv") or die("SKIP: ext/uv required for this test");
?>
--FILE--
<?php

include __DIR__.'/../../vendor/autoload.php';

use Amp\Loop\UvDriver;

$loop = new UvDriver;

$loop->setState('test', new class($loop) {
    private UvDriver $loop;
    private string $handle;
    public function __construct(UvDriver $loop)
    {
        $this->loop = $loop;
        $this->handle = $this->loop->repeat(10, function () {});
    }
    public function __destruct()
    {
        $this->loop->cancel($this->handle);
        print "ok";
    }
});

$loop->delay(0, [$loop, "stop"]);

$loop->run();

?>
--EXPECT--
ok
