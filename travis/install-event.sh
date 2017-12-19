#!/usr/bin/env bash

curl -LS https://pecl.php.net/get/event | tar -xz \
 && pushd event-* \
 && phpize \
 && ./configure --with-event-core --with-event-extra --with-event-pthreads \
 && make \
 && make install \
 && popd \
 && echo "extension=event.so" >> "$(php -r 'echo php_ini_loaded_file();')";
