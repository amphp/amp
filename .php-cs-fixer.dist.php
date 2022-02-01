<?php

$config = new Amp\CodeStyle\Config;
$config->getFinder()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/test');

$config->setCacheFile(__DIR__ . '/.php_cs.cache');

return $config;
