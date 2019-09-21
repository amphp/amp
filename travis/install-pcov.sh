#!/usr/bin/env bash

if [[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
    exit 0
fi

curl -LS https://pecl.php.net/get/pcov | tar -xz \
 && pushd pcov-* \
 && phpize \
 && ./configure --enable-pcov \
 && make \
 && make install \
 && popd \
 && echo "extension=pcov.so" >> "$(php -r 'echo php_ini_loaded_file();')";
