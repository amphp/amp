#!/usr/bin/env bash

curl -LS https://pecl.php.net/get/ev | tar -xz \
 && pushd ev-* \
 && phpize \
 && ./configure \
 && make -j4 \
 && make install \
 && popd \
 && echo "extension=ev.so" >> "$(php -r 'echo php_ini_loaded_file();')";
