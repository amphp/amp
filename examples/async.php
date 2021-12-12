<?php

require __DIR__ . '/../vendor/autoload.php';

use Amp\Future;
use function Amp\async;
use function Amp\delay;

$future1 = async(function () {
    delay(1);

    print 'c';
});

$future2 = async(function () {
    delay(2);

    print 'd';
});

$future3 = async(function () {
    print 'a';
});

$future4 = async(function () {
    Future::complete()->await();

    print 'b';
});

$future1->await();
$future2->await();
$future3->await();

delay(1);

print 'e';

delay(1);

print 'f';
