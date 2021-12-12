<?php

require __DIR__ . '/../vendor/autoload.php';

use function Amp\async;
use function Amp\delay;

$future1 = async(function () {
    delay(1);

    print 'b';
});

$future2 = async(function () {
    delay(2);

    print 'c';
});

$future3 = async(function () {
    delay(0.5);

    print 'a';
});

$future1->await();
$future2->await();
$future3->await();

delay(1);

print 'd';

delay(1);

print 'e';
