# Event Loop Interopability

The purpose of this proposal is to provide a common interface for event loop
implementations. This will allow libraries and components from different
vendors to operate in an event driven architecture, sharing a common event
loop.

## Why Bother?

Some programming languages, such as Javascript, have an event loop that is
native to the execution environment. This allows package vendors to easily
create asynchronous software that uses this native event loop. Although PHP
is historically a synchronous programming environment, it is still possible
to use asynchronous programming techniques. Using these techniques, package
vendors have created PHP event loop implementations that have seen success.

However, as these event loop implementations are from package vendors, it
is not yet possible to create event driven software components that are
independent of the underlying event loop implementation. By creating a
common interface for an event loop, interoperability of this nature will
be possible.

## Goals

The functionality exposed by this interface should include the ability to:

- Watch input streams for available data
- Watch output streams for the ability to perform non-blocking write operations
- Run single and periodic timers
- Listen for signals
- Defer the execution of callables

## Implementations

You can find [available implementations on Packagist](https://packagist.org/providers/async-interop/event-loop-implementation).

## Compatible Packages

You can find [compatible packages on Packagist](https://packagist.org/packages/async-interop/event-loop/dependents).

## Contributors

* [Aaron Piotrowski](https://github.com/trowski)
* [Andrew Carter](https://github.com/AndrewCarterUK)
* [Bob Weinand](https://github.com/bwoebi)
* [Cees-Jan Kiewiet](https://github.com/WyriHaximus)
* [Christopher Pitt](https://github.com/assertchris)
* [Daniel Lowrey](https://github.com/rdlowrey)
* [Niklas Keller](https://github.com/kelunik)
* [Stephen M. Coakley](https://github.com/coderstephen)
