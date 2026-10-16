<?php

final class OxfmtLinterTestCase extends PhutilTestCase
{
    public function testEmptyBatchNeedsNoBinary(): void
    {
        $method = new ReflectionMethod(OxfmtLinter::class, 'buildFutures');
        $method->setAccessible(true);
        $this->assertSameValue([], $method->invoke(new OxfmtLinter(), []));
    }

    public function testFailedFormattingCommandRaisesWithoutAddingMessages(): void
    {
        $root = (string) realpath(Filesystem::createTemporaryDirectory('oxfmt-failure'));
        try {
            $script = $root . '/fake-oxfmt';
            Filesystem::writeFile($script, '#!' . PHP_BINARY . "\n<?php\nexit(2);");
            chmod($script, 0755);
            $engine = new ArcanistConfigurationDrivenLintEngine();
            $engine->setWorkingCopy(ArcanistWorkingCopyIdentity::newFromRootAndConfigFile($root, null, null));
            $linter = new OxfmtLinter();
            $linter->setEngine($engine);
            $linter->setBinary($script);
            $linter->addData('example.js', 'const = ;');
            $caught = false;
            try {
                $linter->willLintPaths(['example.js']);
                $linter->didLintPaths(['example.js']);
            } catch (CommandException $exception) {
                $caught = true;
            }
            $this->assertSameValue(true, $caught);
            $this->assertSameValue([], $linter->getLintMessages());
        } finally {
            Filesystem::remove($root);
        }
    }

    public function testFailuresNeverProduceDestructiveReplacement(): void
    {
        $linter = new OxfmtLinter();
        $linter->addData('example.js', "const = ;\n");
        foreach ([1, 2, 127] as $exitCode) {
            $this->assertSameValue(false, $this->parse($linter, $exitCode, ''));
        }
    }

    public function testFormattingProducesWholeFileAutofix(): void
    {
        $linter = new OxfmtLinter();
        $linter->addData('example.js', "const x={a:1}\n");
        $messages = $this->parseMessages($linter, 0, "const x = { a: 1 };\n");
        $this->assertSameValue(1, count($messages));
        $message = $messages[0];
        $this->assertSameValue('example.js', $message->getPath());
        $this->assertSameValue(1, $message->getLine());
        $this->assertSameValue(1, $message->getChar());
        $this->assertSameValue('OXFMT', $message->getCode());
        $this->assertSameValue('Oxfmt Format', $message->getName());
        $this->assertSameValue(ArcanistLintSeverity::SEVERITY_AUTOFIX, $message->getSeverity());
        $this->assertSameValue("const x={a:1}\n", $message->getOriginalText());
        $this->assertSameValue("const x = { a: 1 };\n", $message->getReplacementText());
        $this->assertSameValue(true, $message->shouldBypassChangedLineFiltering());
    }

    public function testFuturesUseConfiguredBinaryFlagsCwdAndInMemoryData(): void
    {
        $root = (string) realpath(Filesystem::createTemporaryDirectory('oxfmt-test'));
        try {
            $script = $root . '/fake oxfmt';
            Filesystem::writeFile($script, '#!' . PHP_BINARY . "\n<?php\n" .
                'echo json_encode([getcwd(), array_slice($argv, 1), file_get_contents("php://stdin")]);');
            chmod($script, 0755);
            $engine = new ArcanistConfigurationDrivenLintEngine();
            $engine->setWorkingCopy(ArcanistWorkingCopyIdentity::newFromRootAndConfigFile($root, null, null));
            $linter = new OxfmtLinter();
            $linter->setEngine($engine);
            $this->assertSameValue($root . '/node_modules/.bin/oxfmt', $linter->getDefaultBinary());
            $this->assertSameValue('oxfmt', $linter->getLinterConfigurationName());
            $this->assertSameValue('OXFMT', $linter->getLinterName());
            $this->assertSameValue('Oxfmt', $linter->getInfoName());
            $this->assertSameValue('https://oxc.rs/docs/guide/usage/formatter.html', $linter->getInfoURI());
            $this->assertSameValue(true, strpos($linter->getInstallInstructions(), 'npm install --save-dev oxfmt') !== false);
            $linter->setLinterConfigurationValue('bin', $script);
            $linter->setLinterConfigurationValue('flags', ['--config', 'config with spaces.json']);
            $paths = ['first file.js', 'second.js'];
            foreach ($paths as $path) {
                $linter->addData($path, 'in-memory ' . $path);
            }
            $method = new ReflectionMethod($linter, 'buildFutures');
            $method->setAccessible(true);
            $futures = $method->invoke($linter, $paths);
            $this->assertSameValue($paths, array_keys($futures));
            foreach ($futures as $path => $future) {
                list($err, $stdout) = $future->resolve();
                $this->assertSameValue(0, $err);
                $this->assertSameValue(
                    [$root, ['--config', 'config with spaces.json', '--stdin-filepath', $path], 'in-memory ' . $path],
                    json_decode($stdout, true)
                );
            }
        } finally {
            Filesystem::remove($root);
        }
    }

    public function testUnchangedAndEmptyFiles(): void
    {
        foreach (["const x = 1;\n", ''] as $content) {
            $linter = new OxfmtLinter();
            $linter->addData('example.js', $content);
            $this->assertSameValue([], $this->parse($linter, 0, $content));
        }
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    private function assertSameValue($expected, $actual): void
    {
        $this->assertEqual($expected, $actual);
    }

    /** @return array|false */
    private function parse(OxfmtLinter $linter, int $exitCode, string $stdout)
    {
        $method = new ReflectionMethod($linter, 'parseLinterOutput');
        $method->setAccessible(true);
        return $method->invoke($linter, 'example.js', $exitCode, $stdout, '');
    }

    private function parseMessages(OxfmtLinter $linter, int $exitCode, string $stdout): array
    {
        $messages = $this->parse($linter, $exitCode, $stdout);
        if (!is_array($messages)) {
            throw new RuntimeException('Expected lint messages.');
        }
        return $messages;
    }
}
