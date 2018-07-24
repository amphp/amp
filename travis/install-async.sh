#!/usr/bin/env bash

wget https://github.com/concurrent-php/ext-async/archive/master.tar.gz -O /tmp/php-async.tar.gz -q

mkdir php-async && tar -xf /tmp/php-async.tar.gz -C php-async --strip-components=1

pushd php-async
phpize
./configure
make
make install
popd

echo "extension=async" >> "$(php -r 'echo php_ini_loaded_file();')"
