<?php

namespace Amp\Test;

use PHPUnit\Framework\TestCase;

class PsalmTest extends TestCase
{
    /**
     * @requires PHP >= 7.1
     */
    public function test()
    {
        if (\DIRECTORY_SEPARATOR === '\\') {
            self::markTestSkipped('Skipped on Windows');
        }

        $issues = \json_decode(
            \shell_exec('./vendor/bin/psalm.phar --output-format=json --no-progress --config=psalm.examples.xml'),
            true
        );

        foreach ($issues as $issue) {
            $file = \file_get_contents($issue['file_path']);
            $fileLines = \explode("\n", $file);

            if (!\preg_match('(// psalm-expect (.*))', $fileLines[$issue['line_from'] - 2] ?? '', $match)) {
                self::fail('Psalm reports an issue that isn\'t marked as expected: ' . \json_encode(
                        $issue,
                        \JSON_PRETTY_PRINT
                    ));
            }

            $expectedIssues = \array_map('trim', \explode(',', $match[1]));
            if (!\in_array($issue['type'], $expectedIssues, true)) {
                self::fail('Psalm reports an issue that isn\'t marked as expected: ' . \json_encode(
                        $issue,
                        \JSON_PRETTY_PRINT
                    ));
            }
        }

        $this->expectNotToPerformAssertions();
    }
}
