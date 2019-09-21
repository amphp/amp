<?php

function modify(callable $callback)
{
    $file = __DIR__ . '/../composer.json';
    $data = \json_decode(file_get_contents($file));
    $callback($data);
    \file_put_contents($file, \json_encode($data, \JSON_PRETTY_PRINT));
}

$command = $argv[1];

if ($command === 'drop-config') {
    modify(static function ($config) {
        unset($config->config);
    });
} else {
    if ($command === 'configure-pcov') {
        if (PHP_VERSION_ID >= 70100) {
            shell_exec('composer require pcov/clobber ^1');

            modify(static function ($config) {
                $config->scripts = new \stdClass;
                $config->scripts->{'post-autoload-dump'} = '\\pcov\\Clobber::autoload';
            });

            shell_exec('composer dump');
        }
    }
}
