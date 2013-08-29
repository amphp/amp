# Alert

Alert provides both native and libevent event reactors for powering event-driven PHP applications
and servers. The `Alert\Reactor` exposes a simple API allowing performant non-blocking and
asynchronous execution across operating systems and extension environments.

#### DEPENDENCIES

* PHP 5.4+
* (optional) [*PECL libevent*][libevent] for faster evented execution and high-volume descriptor
  observations. Windows libevent extension DLLs available [here][win-libevent]

#### INSTALLATION

###### Git:

```bash
$ git clone https://github.com/rdlowrey/alert.git
```
###### Manual Download:

Manually download from the [tagged release][tags] section.

###### Composer:

```bash
$ php composer.phar require rdlowrey/alert:0.2.*
```

[tags]: https://github.com/rdlowrey/alert/releases "Tagged Releases"
[libevent]: http://pecl.php.net/package/libevent "libevent"
[win-libevent]: http://windows.php.net/downloads/pecl/releases/ "Windows libevent DLLs"
