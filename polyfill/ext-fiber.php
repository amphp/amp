<?php

if (!\extension_loaded('fiber')) {
    require __DIR__ . '/../stubs/Awaitable.php';
    require __DIR__ . '/../stubs/FiberScheduler.php';
}
