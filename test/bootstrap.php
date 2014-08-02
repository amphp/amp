<?php

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function($class) {
    if (strpos($class, 'AlertTest\\') === 0) {
        $name = substr($class, strlen('AlertTest'));
        require __DIR__ . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});
