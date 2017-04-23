#!/usr/bin/env bash

git clone https://github.com/libuv/libuv;
pushd libuv;
git checkout $(git describe --tags);
./autogen.sh;
./configure --prefix=$(dirname `pwd`)/libuv-install;
make;
make install;
popd;
git clone https://github.com/bwoebi/php-uv.git;
pushd php-uv;
phpize;
./configure --with-uv=$(dirname `pwd`)/libuv-install;
make;
make install;
popd;
echo "extension=uv.so" >> "$(php -r 'echo php_ini_loaded_file();')";