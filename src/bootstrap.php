<?php

spl_autoload_register(function($class) {
    if (strpos($class, 'Alert\\') === 0) {
        $name = substr($class, strlen('Alert'));
        require __DIR__ . "/../lib" . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});
