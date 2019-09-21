#!/usr/bin/env bash

set -e

if [[ ${TRAVIS_PHP_VERSION:0:3} == "7.0" ]]; then
    exit 0
fi

# required for pcov/clobber
curl -LS https://pecl.php.net/get/uopz | tar -xz \
 && pushd uopz-* \
 && phpize \
 && ./configure --enable-uopz \
 && make -j4 \
 && make install \
 && popd \
 && echo "extension=uopz.so" >> "$(php -r 'echo php_ini_loaded_file();')";

curl -LS https://pecl.php.net/get/pcov | tar -xz \
 && pushd pcov-* \
 && phpize \
 && ./configure --enable-pcov \
 && make \
 && make install \
 && popd \
 && echo "extension=pcov.so" >> "$(php -r 'echo php_ini_loaded_file();')";
