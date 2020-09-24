<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Delayed;
use function Amp\await;

// Note that the closure declares void as a return type, not Promise or Generator.
$result = await(new Delayed(1000, 42));
\var_dump($result);
