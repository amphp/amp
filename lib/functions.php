<?php

namespace Alert;

function immediately(callable $func) {
    return ReactorFactory::select()->immediately($func);
}

function once(callable $func, $msDelay) {
    return ReactorFactory::select()->once($func, $msDelay);
}

function repeat(callable $func, $msDelay) {
    return ReactorFactory::select()->repeat($func, $msDelay);
}

function at(callable $func, $timeString) {
    return ReactorFactory::select()->at($func, $timeString);
}

function enable($watcherId) {
    ReactorFactory::select()->enable($watcherId);
}

function disable($watcherId) {
    ReactorFactory::select()->disable($watcherId);
}

function cancel($watcherId) {
    ReactorFactory::select()->cancel($watcherId);
}

function onReadable($stream, callable $function, $enableNow = true) {
    return ReactorFactory::select()->onReadable($stream, $function, $enableNow);
}

function onWritable($stream, callable $function, $enableNow = true) {
    return ReactorFactory::select()->onWritable($stream, $function, $enableNow);
}

function watchStream($stream, $flags, callable $func) {
    return ReactorFactory::select()->watchStream($stream, $flags, $func);
}

function tick() {
    ReactorFactory::select()->tick();
}

function run(callable $onStart = null) {
    ReactorFactory::select()->run($onStart);
}

function stop() {
    ReactorFactory::select()->stop();
}

function reactor() {
    return ReactorFactory::select();
}
