<?php

namespace Amp\Test;

use Amp;
use Amp\Emitter;
use Amp\Message;
use Amp\Success;
use Amp\Loop;

class MessageTest extends \PHPUnit\Framework\TestCase {
    public function testBufferingAll() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->resolve();

            $result = yield $message;

            $this->assertSame(\implode($values), $result);
        });
    }

    public function testFullStreamConsumption() {
        Loop::run(function () use (&$invoked) {
            $values = ["abc", "def", "ghi"];
            $result = 1;

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            Loop::delay(5, function () use ($emitter, $result) {
                $emitter->resolve($result);
            });

            $buffer = "";
            while (yield $message->advance()) {
                $buffer .= $message->getCurrent();
            }

            $this->assertSame(\implode($values), $buffer);
            $this->assertSame("", yield $message);
            $this->assertSame($result, $message->getResult());
        });
    }

    public function testFastResolvingStream() {
        Loop::run(function () {
            $values = ["abc", "def", "ghi"];
            $result = 1;

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            foreach ($values as $value) {
                $emitter->emit($value);
            }

            $emitter->resolve($result);

            $emitted = [];
            while (yield $message->advance()) {
                $emitted[] = $message->getCurrent();
            }

            $this->assertSame([\implode($values)], $emitted);
            $this->assertSame(\implode($values), yield $message);
            $this->assertSame($result, $message->getResult());
        });
    }
    public function testPartialStreamConsumption() {
        Loop::run(function () {
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
        });
    }

    public function testFailingStream() {
        Loop::run(function () {
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
        });
    }

    public function testEmptyStream() {
        Loop::run(function () {
            $value = 1;
            $message = new Message(new Success($value));

            $this->assertFalse(yield $message->advance());
            $this->assertSame($value, $message->getResult());
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has resolved
     */
    public function testAdvanceAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->resolve();

            for ($i = 0; $i < 3; ++$i) {
                yield $message->advance();
            }
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has resolved
     */
    public function testGetCurrentAfterCompletion() {
        Loop::run(function () {
            $value = "abc";

            $emitter = new Emitter;
            $message = new Message($emitter->stream());

            $emitter->emit($value);
            $emitter->resolve();

            while (yield $message->advance());

            $message->getCurrent();
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has not resolved
     */
    public function testGetResultBeforeCompletion() {
        Loop::run(function () {
            $emitter = new Emitter;
            $message = new Message($emitter->stream());
            $message->getResult();
        });
    }
}
