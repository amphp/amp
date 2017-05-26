#!/usr/bin/env bash

wget https://github.com/libuv/libuv/archive/v1.x.tar.gz -O /tmp/libuv.tar.gz -q &
wget https://github.com/bwoebi/php-uv/archive/master.tar.gz -O /tmp/php-uv.tar.gz -q &
wait

mkdir libuv && tar -xf /tmp/libuv.tar.gz -C libuv --strip-components=1
mkdir php-uv && tar -xf /tmp/php-uv.tar.gz -C php-uv --strip-components=1

pushd libuv;
./autogen.sh
./configure --prefix=$(dirname `pwd`)/libuv-install
make
make install
popd

pushd php-uv
phpize
./configure --with-uv=$(dirname `pwd`)/libuv-install
make
make install
popd

echo "extension=uv.so" >> "$(php -r 'echo php_ini_loaded_file();')"
