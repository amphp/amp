---
layout: docs
title: Struct
permalink: /utils/struct
---
A struct is a generic computer science term for an object composed of public properties. The `\Amp\Struct` trait
is intended to make using public properties a little safer by throwing an `\Error` when undefined properties
are attempted to be written or read.

PHP allows for dynamic creation of public properties. This can lead to insidious bugs created by typos related to
writing to and reading from public properties. One common solution to this problem is to make all properties private and
provide public setter and getter methods which control access to the underlying properties. However effective this
solution may be, it requires that additional code be written and subsequently tested for the setter and getter methods.

Let's try some examples with anonymous classes to demonstrate the advantages of using the `\Amp\Struct` trait. Running
the following code will not error; although, the typo will likely create some unexpected behavior:

```php
$obj = new class {
    public $foo = null;
};

$obj->fooo = "bar";
```

If you were to access the `$foo` property of the `$obj` object after the above code, you might expect the value
to be `"bar"` when it would actually be `NULL`.

When a class uses the `\Amp\Struct` trait, an `\Error` will be thrown when attempting to access a property not defined
in the class definition. For example, the code below will throw an `\Error` with some context that attempts to help 
diagnose the issue.

```php
$obj = new class {
    use Amp\Struct;

    public $foo = null;
};

$obj->fooo = "bar";
```

The message for the thrown `\Error` will be similar to:
*Uncaught Error: class@anonymous@php shell code0x10ee8005b property "fooo" does not exist ... did you mean "foo?"*

Although, an `\Error` being thrown in your code may cause some havoc, it will not allow for unpredictable
behavior caused by the use of properties which are not part of the class definition.
