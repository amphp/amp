<?php

namespace Amp;

use Interop\Async\{ Loop, Promise\ErrorHandler };

ErrorHandler::set(function (\Throwable $exception) {
    Loop::defer(function () use ($exception) {
        throw $exception;
    });
});
