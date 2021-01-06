<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use function Amp\await;

$result = await(new Delayed(1000, 42));
\var_dump($result);
