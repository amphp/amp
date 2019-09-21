<?php

$file = __DIR__ . '/../composer.json';
$data = \json_decode(file_get_contents($file));

unset($data->config);

\file_put_contents($file, \json_encode($data, \JSON_PRETTY_PRINT));
