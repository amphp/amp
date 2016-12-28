<?php

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::NONE_LEVEL)
    ->fixers([
        "psr2",
        "-braces",
        "-psr0",
    ])
	->finder(
		Symfony\CS\Finder\DefaultFinder::create()
			->in(__DIR__ . "/lib")
			->in(__DIR__ . "/test")
	)
;
