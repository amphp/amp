<?php

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::NONE_LEVEL)
    ->fixers([
        "psr1",
        "psr2",
        "-braces",
        "-psr0",
        "ordered_use",
        "short_array_syntax",
        "no_useless_else",
        "no_useless_return",
        "multiline_spaces_before_semicolon",
        "combine_consecutive_unsets",
        "unused_use",
        "whitespacy_lines",
        "spaces_cast",
        "single_blank_line_before_namespace",
        "short_scalar_cast",
        "short_bool_cast",
        "self_accessor",
        "remove_leading_slash_use",
        "phpdoc_types",
        "extra_empty_lines",
        "duplicate_semicolon",
    ])
	->finder(
		Symfony\CS\Finder\DefaultFinder::create()
			->in(__DIR__ . "/lib")
			->in(__DIR__ . "/test")
	)
;
