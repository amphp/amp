# Alert

Alert provides native and libevent event reactors for powering event-driven PHP applications
and servers. The `Alert\Reactor` exposes a simple API for performant non-blocking and asynchronous
execution in a wide variety of PHP environments.

#### FEATURES

Alert adds the following functionality to the evented PHP space:

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
