<?php

/**
 * Differs from primary autoloader by routing classes suffixed with "Test"
 * to the "test/" directory instead of "lib/" ...
 */
spl_autoload_register(function($class) {
    if (strpos($class, 'Alert\\') === 0) {
        $dir = strcasecmp(substr($class, -4), 'Test') ? 'lib' : 'test';
        $name = substr($class, strlen('Alert'));
        require __DIR__ . '/../' . $dir . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});
