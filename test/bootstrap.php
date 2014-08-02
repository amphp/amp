<?php

spl_autoload_register(function($class) {
    if (strpos($class, 'Alert\\') === 0) {
        $dir = strcasecmp(substr($class, -4), 'Test') ? 'lib' : 'test';
        $name = substr($class, strlen('Alert'));
        require __DIR__ . '/../' . $dir . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});
