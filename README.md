# Alert

Alert provides native and libevent event reactors for powering event-driven PHP applications
and servers. 

#### WHY?

Buffer and event-emmitter abstractions -- though user-friendly -- are unfortunately slow in userland.
Performant PHP servers cannot compete (in terms of speed) with the likes of Node.js where such features
are compiled. Alert eschews such features and provides *only* non-blocking IO and timer events to avoid
enforcing OOP slowness on the overlaid application. It's a stripped down, no-frills event reactor 
without a learning curve that "just works."

#### FEATURES

Alert adds the following functionality to PHP's non-blocking IO space:

- Pausing/resuming *individual* timers or stream IO observers
- Assigning multiple watchers for individual streams

#### DEPENDENCIES

* PHP 5.4+
* (optional) [*PECL libevent*][libevent] for faster evented execution and high-volume descriptor
  reactions. Windows libevent extension DLLs are available [here][win-libevent]

#### INSTALLATION

###### Git:

```bash
$ git clone https://github.com/rdlowrey/alert.git
```
###### Manual Download:

Manually download from the [tagged release][tags] section.

###### Composer:

```bash
$ php composer.phar require rdlowrey/alert:0.3.*
```

[tags]: https://github.com/rdlowrey/alert/releases "Tagged Releases"
[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"
