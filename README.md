# Event Loop Tests

This package provides a quite extensive phpunit test suite to be used against `Loop\Driver` implementations from the [async-interop/event-loop](https://github.com/async-interop/event-loop) package.

## Usage

```php
class MyDriverTest extends \Interop\Async\Loop\Test {
    function getFactory() {
        return new MyDriverFactory;
    }
}
```

That's it. Put it in your tests folder with an appropriate phpunit setup and run it.