<?php

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Alert\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/src/$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});
