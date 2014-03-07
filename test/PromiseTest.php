<?php

namespace Alert;

class PromiseTest extends \PHPUnit_Framework_TestCase {

    public function testNestedFutureResolution() {
        $reactor = new NativeReactor;
        $promise1 = new Promise;
        $future1 = $promise1->getFuture();
        $future1->onComplete(function(Future $f) use ($reactor) {
            $this->assertSame(42, $f->getValue());
            $reactor->stop();
        });

        $reactor->run(function() use ($promise1) {
            $promise2 = new Promise;
            $future2 = $promise2->getFuture();
            $promise1->succeed($future2);
            $promise2->succeed(42);
        });
    }

    public function testSucceed() {
        $reactor = new NativeReactor;
        $promise = new Promise;
        $future = $promise->getFuture();
        $future->onComplete(function(Future $f) use ($reactor) {
            $this->assertSame(42, $f->getValue());
            $reactor->stop();
        });

        $reactor->run(function() use ($promise) {
            $promise->succeed(42);
        });
    }

    public function testFail() {
        $reactor = new NativeReactor;
        $promise = new Promise;
        $future = $promise->getFuture();
        $error = new \Exception("test");
        $future->onComplete(function(Future $f) use ($reactor, $error) {
            $this->assertFalse($f->succeeded());
            $this->assertSame($error, $f->getError());
            $reactor->stop();
        });

        $reactor->run(function() use ($promise, $error) {
            $promise->fail($error);
        });
    }

    public function testResolveError() {
        $reactor = new NativeReactor;
        $promise = new Promise;
        $future = $promise->getFuture();
        $error = new \Exception("test");
        $future->onComplete(function(Future $f) use ($reactor, $error) {
            $this->assertFalse($f->succeeded());
            $this->assertSame($error, $f->getError());
            $reactor->stop();
        });

        $reactor->run(function() use ($promise, $error) {
            $promise->resolve($error, $value = NULL);
        });
    }

    public function testResolveSuccess() {
        $reactor = new NativeReactor;
        $promise = new Promise;
        $future = $promise->getFuture();
        $error = new \Exception("test");
        $future->onComplete(function(Future $f) use ($reactor) {
            $this->assertSame(42, $f->getValue());
            $reactor->stop();
        });

        $reactor->run(function() use ($promise) {
            $promise->resolve($error = NULL, $value = 42);
        });
    }
}
