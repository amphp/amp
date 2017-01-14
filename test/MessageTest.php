<?php

namespace Amp\Test;

use Amp;
use Amp\{ Emitter, Message };
use AsyncInterop\Loop;

class MessageTest extends \PHPUnit_Framework_TestCase {
    public function testBufferingAll() {
        Loop::execute(Amp\wrap(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->resolve();

            $result = yield $message;

            $this->assertSame(\implode($values), $result);
        }));
    }

    public function testFullStreamConsumption() {
        Loop::execute(Amp\wrap(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $buffer = "";
            for ($i = 0; $i < \count($values) && yield $message->advance(); ++$i) {
                $buffer .= $message->getCurrent();
            }

            $emitter->resolve();

            $this->assertSame(\implode($values), $buffer);
            $this->assertSame("", yield $message);
        }));
    }

    public function testPartialStreamConsumption() {
        Loop::execute(Amp\wrap(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $buffer = "";
            for ($i = 0; $i < 1 && yield $message->advance(); ++$i) {
                $buffer .= $message->getCurrent();
            }

            $this->assertSame(\array_shift($values), $buffer);

            $emitter->resolve();

            $this->assertSame(\implode($values), yield $message);
        }));
    }

    public function testFailingStream() {
        Loop::execute(Amp\wrap(function () {
            $exception = new \Exception;
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->fail($exception);

            try {
                while (yield $message->advance()) {
                    $this->assertSame($value, $message->getCurrent());
                }
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        }));
    }
}
