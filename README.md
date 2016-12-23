# Promise Tests

This package provides a quite extensive phpunit test suite to be used against `Promise` implementations from the [async-interop/promise](https://github.com/async-interop/promise) package.

## Usage

```php
class MyDriverTest extends \Interop\Async\Promise\Test {
    function getFactory() {
        return new MyDriverFactory;
    }
    
    function getPromise() {
        $resolver = new MyPromiseResolver;
        return [
            $resolver->promise(),
            function($v) use ($resolver) { $resolver->succeed($v); },
            function($e) use ($resolver) { $resolver->fail($e); },
        ];
    }
}
```

That's it. Put it in your tests folder with an appropriate phpunit setup and run it.
