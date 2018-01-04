<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Promise;
use PHPUnit\Framework\TestCase;

class AsyncGenerator
{
    use \Amp\Internal\Generator {
        yield as public;
        complete as public;
        fail as public;
    }
}

class GeneratorTraitTest extends TestCase
{
    /** @var \Amp\Test\AsyncGenerator */
    private $generator;

    public function setUp()
    {
        $this->generator = new AsyncGenerator;
    }

    public function testYield()
    {
        Loop::run(function () {
            $value = 'Emitted Value';

            $promise = $this->generator->yield($value);
            $iterator = $this->generator->iterate();

            $this->assertSame([$value, 0], yield $iterator->continue());

            $this->assertInstanceOf(Promise::class, $promise);
        });
    }

    /**
     * @depends testYield
     */
    public function testYieldWithKey()
    {
        Loop::run(function () {
            $value = 'Emitted value';

            $promise = $this->generator->yield($value);
            $iterator = $this->generator->iterate();

            $this->assertSame([$value, 0], yield $iterator->continue());

            $this->assertInstanceOf(Promise::class, $promise);
        });
    }


    /**
     * @depends testYield
     * @expectedException \Error
     * @expectedExceptionMessage Flows cannot yield values after calling complete
     */
    public function testYieldAfterComplete()
    {
        $this->generator->complete();
        $this->generator->yield(1);
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The prior promise returned must resolve before invoking this method again
     */
    public function testDoubleAdvance()
    {
        $iterator = $this->generator->iterate();
        $iterator->continue();
        $iterator->continue();
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Flow has already been completed
     */
    public function testDoubleComplete()
    {
        $this->generator->complete();
        $this->generator->complete();
    }

    public function testDestroyingIteratorRelievesBackPressure()
    {
        $iterator = $this->generator->iterate();

        $invoked = 0;
        $onResolved = function () use (&$invoked) {
            $invoked++;
        };

        foreach (\range(1, 5) as $value) {
            $promise = $this->generator->yield($value);
            $promise->onResolve($onResolved);
        }

        $this->assertSame(0, $invoked);

        unset($iterator);

        $this->assertSame(5, $invoked);
    }

    /**
     * @depends testDestroyingIteratorRelievesBackPressure
     * @expectedException \Amp\DisposedException
     * @expectedExceptionMessage The flow has been disposed
     */
    public function testYieldAfterDisposal()
    {
        Loop::run(function () {
            $iterator = $this->generator->iterate();
            unset($iterator);
            yield $this->generator->yield(1);
        });
    }
}
