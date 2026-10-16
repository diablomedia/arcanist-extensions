<?php

final class LintMessageBuilderTestCase extends PhutilTestCase
{
    public function testAddedLine(): void
    {
        $messages = $this->buildMessages("--- Original\n+++ New\n@@ -1,2 +1,3 @@\n <?php\n+\n echo 1;\n");

        $this->assertSameValue([2], array_keys($messages));
        $this->assertSameValue(null, $messages[2]->getOriginalText());
        $this->assertSameValue('', $messages[2]->getReplacementText());
    }

    public function testEmptyDiff(): void
    {
        $this->assertSameValue([], $this->buildMessages(''));
    }

    public function testMultipleHunksAndMissingFinalNewline(): void
    {
        $diff = implode("\n", [
            '--- Original',
            '+++ New',
            '@@ -2,1 +2,1 @@',
            '-$first=1;',
            '+$first = 1;',
            '@@ -10,1 +10,1 @@',
            '-$last=2;',
            '\ No newline at end of file',
            '+$last = 2;',
            '\ No newline at end of file',
            '',
        ]);

        $messages = $this->buildMessages($diff);
        $this->assertSameValue([2, 10], array_keys($messages));
        $this->assertSameValue('$first=1;', $messages[2]->getOriginalText());
        $this->assertSameValue('$first = 1;', $messages[2]->getReplacementText());
        $this->assertSameValue('$last=2;', $messages[10]->getOriginalText());
        $this->assertSameValue('$last = 2;', $messages[10]->getReplacementText());
    }

    public function testRemovedLine(): void
    {
        $messages = $this->buildMessages("--- Original\n+++ New\n@@ -1,3 +1,2 @@\n <?php\n-// remove me\n echo 1;\n");

        $this->assertSameValue([2], array_keys($messages));
        $this->assertSameValue('// remove me', $messages[2]->getOriginalText());
        $this->assertSameValue(null, $messages[2]->getReplacementText());
    }

    public function testReplacementPreservesMessageDetailsAndLineEndings(): void
    {
        foreach (["\n", "\r\n"] as $lineEnding) {
            $diff = implode($lineEnding, [
                '--- Original',
                '+++ New',
                '@@ -1,3 +1,3 @@',
                ' <?php',
                '-$value=1;',
                '+$value = 1;',
                ' echo $value;',
                '',
            ]);

            $messages = $this->buildMessages($diff);
            $this->assertSameValue([2], array_keys($messages));
            $message = $messages[2];
            $this->assertSameValue('example.php', $message->getPath());
            $this->assertSameValue('php-cs-fixer', $message->getCode());
            $this->assertSameValue('binary_operator_spaces, blank_line_after_opening_tag', $message->getName());
            $this->assertSameValue(ArcanistLintSeverity::SEVERITY_WARNING, $message->getSeverity());
            $this->assertSameValue(2, $message->getLine());
            $this->assertSameValue(1, $message->getChar());
            $this->assertSameValue('$value=1;', $message->getOriginalText());
            $this->assertSameValue('$value = 1;', $message->getReplacementText());
        }
    }

    protected function willRunTests(): void
    {
        // Register Composer after Arcanist so it can load dependency classes first.
        require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
    }

    private function assertSameValue(mixed $expected, mixed $actual): void
    {
        $this->assertEqual($expected, $actual);
    }

    /** @return ArcanistLintMessage[] */
    private function buildMessages(string $diff): array
    {
        return (new LintMessageBuilder())->buildLintMessages('example.php', [
            'diff'          => $diff,
            'appliedFixers' => ['binary_operator_spaces', 'blank_line_after_opening_tag'],
        ]);
    }
}
