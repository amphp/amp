<?php /** @noinspection ForgottenDebugOutputInspection */

use Amp\Timing;

require __DIR__ . '/../vendor/autoload.php';

$timing = new Timing;
$timing->start('amphp.http-client.request');
\usleep(\random_int(1, 10) * 1000);
$timing->start('amphp.dns.resolution');
\usleep(\random_int(1, 10) * 1000);
$timing->end('amphp.dns.resolution');
\usleep(\random_int(1, 10) * 1000);
$timing->start('amphp.socket.connect');
\usleep(\random_int(1, 10) * 1000);
$timing->end('amphp.socket.connect');
\usleep(\random_int(1, 10) * 1000);

var_dump($timing);

$timing->end('amphp.http-client.request');

var_dump($timing);
