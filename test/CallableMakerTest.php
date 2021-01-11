<?php

namespace Amp\Test;

class CallableMaker
{
    use \Amp\CallableMaker {
        callableFromInstanceMethod as public;
        callableFromStaticMethod as public;
    }

    public function instanceMethod(): string
    {
        return __METHOD__;
    }

    public static function staticMethod(): string
    {
        return __METHOD__;
    }
}

class CallableMakerTest extends BaseTest
{
    /** @var CallableMaker */
    private $maker;

    public function setUp(): void
    {
        $this->maker = new CallableMaker;
    }

    public function testCallableFromInstanceMethod(): void
    {
        $callable = $this->maker->callableFromInstanceMethod("instanceMethod");
        self::assertIsCallable($callable);
        self::assertSame(\sprintf("%s::%s", CallableMaker::class, "instanceMethod"), $callable());
    }

    public function testCallableFromStaticMethod(): void
    {
        $callable = $this->maker->callableFromInstanceMethod("staticMethod");
        self::assertIsCallable($callable);
        self::assertSame(\sprintf("%s::%s", CallableMaker::class, "staticMethod"), $callable());
    }
}
