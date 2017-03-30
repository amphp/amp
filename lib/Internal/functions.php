<?php

namespace Amp\Internal;

function formatStacktrace(array $trace): string {
    return implode("\n", array_map(function ($e, $i) {
        $line = "#{$i} {$e['file']}:{$e['line']} ";

        if ($e["type"]) {
            $line .= $e["class"] . $e["type"];
        }

        return $line . $e["function"] . "()";
    }, $trace, array_keys($trace)));
}