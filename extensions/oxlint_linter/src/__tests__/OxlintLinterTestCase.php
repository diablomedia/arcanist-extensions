<?php

final class OxlintLinterTestCase extends PhutilTestCase
{
    public function testBatchLifecycleAndCommandFailures(): void
    {
        $root = (string) realpath(Filesystem::createTemporaryDirectory('oxlint-test'));
        try {
            $script = $root . '/fake oxlint';
            $log    = $root . '/invocation.json';
            $report = $root . '/report.json';
            Filesystem::writeFile($report, $this->fixture('diagnostics'));
            Filesystem::writeFile($script, '#!' . PHP_BINARY . "\n<?php\n" .
                'file_put_contents(__DIR__ . "/invocation.json", json_encode([getcwd(), array_slice($argv, 1)]));' .
                'echo file_get_contents(__DIR__ . "/report.json"); exit(1);');
            chmod($script, 0755);
            $engine = new ArcanistConfigurationDrivenLintEngine();
            $engine->setWorkingCopy(ArcanistWorkingCopyIdentity::newFromRootAndConfigFile($root, null, null));
            $linter = new OxlintLinter();
            $linter->setEngine($engine);
            $this->assertSameValue($root . '/node_modules/.bin/oxlint', $linter->getDefaultBinary());
            $this->assertSameValue('oxlint', $linter->getLinterConfigurationName());
            $this->assertSameValue('OXLINT', $linter->getLinterName());
            $this->assertSameValue('Oxlint', $linter->getInfoName());
            $this->assertSameValue('https://oxc.rs/docs/guide/usage/linter.html', $linter->getInfoURI());
            $this->assertSameValue(true, strpos($linter->getInstallInstructions(), 'npm install --save-dev oxlint') !== false);
            $linter->setLinterConfigurationValue('bin', $script);
            $linter->setLinterConfigurationValue('flags', ['--config', 'config with spaces.json']);
            $paths  = ['first file.js', 'second.js', '--leading-dash.js'];
            $method = new ReflectionMethod($linter, 'buildFutures');
            $method->setAccessible(true);
            $futures = $method->invoke($linter, $paths);
            $this->assertSameValue(['first file.js'], array_keys($futures));
            $linter->willLintPaths($paths);
            $linter->didLintPaths($paths);
            $this->assertSameValue(3, count($linter->getLintMessages()));
            $this->assertSameValue(
                [$root, array_merge(['--format=json', '--no-error-on-unmatched-pattern', '--config', 'config with spaces.json', '--'], $paths)],
                json_decode(Filesystem::readFile($log), true)
            );
            Filesystem::writeFile($report, '{"diagnostics":[]}');
            $this->assertCommandFails($linter, $paths);
            Filesystem::writeFile($report, 'Failed to parse oxlint configuration file.');
            $this->assertCommandFails($linter, $paths);
        } finally {
            Filesystem::remove($root);
        }
    }

    public function testCapturedBatchDiagnostics(): void
    {
        $messages = $this->parseMessages(1, $this->fixture('diagnostics'));
        $this->assertSameValue(3, count($messages));
        $byCode = [];
        foreach ($messages as $message) {
            $byCode[$message->getCode()] = $message;
        }
        $error = $byCode['eslint(no-debugger)'];
        $this->assertSameValue('first file.js', $error->getPath());
        $this->assertSameValue(2, $error->getLine());
        $this->assertSameValue(1, $error->getChar());
        $this->assertSameValue('eslint(no-debugger)', $error->getName());
        $this->assertSameValue('`debugger` statement is not allowed', $error->getDescription());
        $this->assertSameValue(ArcanistLintSeverity::SEVERITY_ERROR, $error->getSeverity());
        $warning = $byCode['eslint(no-unused-vars)'];
        $this->assertSameValue(ArcanistLintSeverity::SEVERITY_WARNING, $warning->getSeverity());
        $this->assertSameValue(7, $warning->getChar());
        $this->assertSameValue('second.js', $byCode['eslint(no-constant-condition)']->getPath());
    }

    public function testCapturedParserErrorWithoutRuleCode(): void
    {
        $messages = $this->parseMessages(1, $this->fixture('syntax'));
        $this->assertSameValue(1, count($messages));
        $this->assertSameValue('OXLINT', $messages[0]->getCode());
        $this->assertSameValue('syntax.js', $messages[0]->getPath());
        $this->assertSameValue(1, $messages[0]->getLine());
        $this->assertSameValue(7, $messages[0]->getChar());
    }

    public function testCleanReportAndExpectedExitCodes(): void
    {
        $this->assertSameValue([], $this->parse(0, $this->fixture('clean')));
        $this->assertSameValue(false, $this->parse(1, $this->fixture('clean')));
        foreach ([2, 127, 137] as $exitCode) {
            $this->assertSameValue(false, $this->parse($exitCode, $this->fixture('diagnostics')));
        }
        $warning = json_encode(['diagnostics' => [['message' => 'Warning', 'severity' => 'warning']]], JSON_THROW_ON_ERROR);
        // Warnings normally exit 0, but --deny-warnings/--max-warnings may exit 1.
        foreach ([0, 1] as $exitCode) {
            $messages = $this->parseMessages($exitCode, $warning);
            $this->assertSameValue(1, count($messages));
            $this->assertSameValue('fallback.js', $messages[0]->getPath());
            $this->assertSameValue(1, $messages[0]->getLine());
            $this->assertSameValue(1, $messages[0]->getChar());
        }
    }

    public function testEmptyBatchNeedsNoBinary(): void
    {
        $method = new ReflectionMethod(OxlintLinter::class, 'buildFutures');
        $method->setAccessible(true);
        $this->assertSameValue([], $method->invoke(new OxlintLinter(), []));
    }

    public function testMalformedOutputFailsClosed(): void
    {
        foreach (['', 'not json', 'null', '[]', '{}', '{"diagnostics":null}', '{"diagnostics":true}', '{"diagnostics":{"invalid":[]}}'] as $output) {
            $this->assertSameValue(false, $this->parse(0, $output));
        }
        $valid   = ['message' => 'Test', 'severity' => 'error'];
        $invalid = [
            null,
            [],
            ['message' => 123, 'severity' => 'error'],
            ['message' => 'Test', 'severity' => 'info'],
            $valid + ['filename' => []],
            $valid + ['code' => []],
            $valid + ['labels' => false],
            $valid + ['labels' => ['bad']],
            $valid + ['labels' => [['span' => 'bad']]],
            $valid + ['labels' => [['span' => ['line' => 0]]]],
            $valid + ['labels' => [['span' => ['line' => '2']]]],
            $valid + ['labels' => [['span' => ['column' => -1]]]],
        ];
        foreach ($invalid as $diagnostic) {
            // Do not return a partial success when a later diagnostic is malformed.
            $this->assertSameValue(false, $this->parse(1, json_encode(['diagnostics' => [$valid, $diagnostic]], JSON_THROW_ON_ERROR)));
        }
    }

    /**
     * @param list<string> $paths
     */
    private function assertCommandFails(OxlintLinter $linter, array $paths): void
    {
        $caught = false;
        try {
            $linter->willLintPaths($paths);
            $linter->didLintPaths($paths);
        } catch (CommandException $exception) {
            $caught = true;
        }
        $this->assertSameValue(true, $caught);
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    private function assertSameValue($expected, $actual): void
    {
        $this->assertEqual($expected, $actual);
    }

    private function fixture(string $name): string
    {
        return Filesystem::readFile(__DIR__ . '/fixtures/' . $name . '.json');
    }

    /** @return array|false */
    private function parse(int $exitCode, string $stdout)
    {
        $method = new ReflectionMethod(OxlintLinter::class, 'parseLinterOutput');
        $method->setAccessible(true);
        return $method->invoke(new OxlintLinter(), 'fallback.js', $exitCode, $stdout, '');
    }

    private function parseMessages(int $exitCode, string $stdout): array
    {
        $messages = $this->parse($exitCode, $stdout);
        if (!is_array($messages)) {
            throw new RuntimeException('Expected lint messages.');
        }
        return $messages;
    }
}
