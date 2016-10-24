# Awaitable Tests

This package provides a quite extensive phpunit test suite to be used against `Awaitable` implementations from the [async-interop/awaitable](https://github.com/async-interop/awaitable) package.

## Usage

```php
class MyDriverTest extends \Interop\Async\Awaitable\Test {
    function getFactory() {
        return new MyDriverFactory;
    }
    
    function getAwaitable() {
        $resolver = new MyAwaitableResolver;
        return [
            $resolver->getAwaitable(),
            function($v) use ($resolver) { $resolver->succeed($v); },
            function($e) use ($resolver) { $resolver->fail($e); },
        ];
    }
}
```

That's it. Put it in your tests folder with an appropriate phpunit setup and run it.