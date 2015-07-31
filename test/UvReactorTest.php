<?php

namespace Amp\Test;

use Amp\UvReactor;

class UvReactorTest extends ReactorTest {
    protected function setUp() {
        if (extension_loaded("uv")) {
            \Amp\reactor($assign = new UvReactor);
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    public function testGetLoop() {
        $result = \Amp\reactor()->getLoop();
        $this->assertInternalType("resource", $result);
    }

    public function testOnSignalWatcherKeepAliveRunResult() {
        \Amp\run(function () {
            \Amp\onSignal(\Uv::SIGUSR1, function () {
                // empty
            }, $options = ["keep_alive" => false]);
        });
    }

    /**
     * We need to override the default ReactorTest function to use the correct signal constant
     */
    public function provideRegistrationArgs() {
        $result = [
            ["immediately", [function () {}]],
            ["once",        [function () {}, 5000]],
            ["repeat",      [function () {}, 5000]],
            ["onWritable",  [\STDOUT, function () {}]],
            ["onReadable",  [\STDIN, function () {}]],
        ];

        if (\extension_loaded("uv")) {
            $result[] = ["onSignal",    [\Uv::SIGUSR1, function () {}]];
        }

        return $result;
    }
}
