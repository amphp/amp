#!/usr/bin/env bash

git clone https://github.com/amphp/ext-fiber \
 && pushd ext-fiber \
 && phpize \
 && ./configure \
 && make -j4 \
 && make install \
 && popd \
 && echo "extension=fiber.so" >> "$(php -r 'echo php_ini_loaded_file();')";
