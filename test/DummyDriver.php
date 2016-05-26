<?php

namespace Interop\Async\Loop\Test;

class DummyDriver implements \Interop\Async\Loop\Driver
{
    public $defers;
    public $handler;
    public static $id = "a";

    public function run() {
        while (list($defer, $data) = array_shift($this->defers)) {
            try {
                $defer(self::$id++, $data);
            } catch (Exception $e) {
                if ($handler = $this->handler) {
                    $handler($e);
                } else {
                    throw $e;
                }
            }
        }
    }

    public function defer(callable $callback, $data = null) {
        $this->defers[] = [$callback, $data];
    }

    public function setErrorHandler(callable $callback = null) {
        $this->handler = $callback;
    }

    public function stop() {}
    public function delay($delay, callable $callback, $data = null) { return self::$id++; }
    public function repeat($interval, callable $callback, $data = null) { return self::$id++; }
    public function onReadable($stream, callable $callback, $data = null) { return self::$id++; }
    public function onWritable($stream, callable $callback, $data = null) { return self::$id++; }
    public function onSignal($signo, callable $callback, $data = null) { return self::$id++; }
    public function enable($watcherId) {}
    public function disable($watcherId) {}
    public function cancel($watcherId) {}
    public function reference($watcherId) {}
    public function unreference($watcherId) {}
    public function info() {}
    public function getHandle() {}
}
