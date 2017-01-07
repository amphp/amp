<?php

namespace Amp;

use AsyncInterop\{ Loop, Promise\ErrorHandler };

ErrorHandler::set(function (\Throwable $exception) {
    Loop::defer(function () use ($exception) {
        throw $exception;
    });
});
