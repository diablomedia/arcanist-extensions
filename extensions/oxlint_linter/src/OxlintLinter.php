<?php

final class OxlintLinter extends ArcanistExternalLinter
{
    public function getDefaultBinary()
    {
        return $this->getProjectRoot() . '/node_modules/.bin/oxlint';
    }

    public function getInfoName()
    {
        return 'Oxlint';
    }

    public function getInfoURI(): string
    {
        return 'https://oxc.rs/docs/guide/usage/linter.html';
    }

    public function getInstallInstructions()
    {
        return 'Install oxlint in your project with `npm install --save-dev oxlint` ' .
            '(or the equivalent for your package manager), or configure "bin" in .arclint.';
    }

    public function getLinterConfigurationName(): string
    {
        return 'oxlint';
    }

    public function getLinterName(): string
    {
        return 'OXLINT';
    }

    protected function buildFutures(array $paths): array
    {
        if (!$paths) {
            return [];
        }

        // Initialize JS plugins once per batch, rather than once per file.
        $future = new ExecFuture(
            '%C %Ls -- %Ls',
            $this->getExecutableCommand(),
            $this->getCommandFlags(),
            $paths
        );
        $future->setCWD($this->getProjectRoot());

        return [$paths[0] => $future];
    }

    protected function getMandatoryFlags()
    {
        return ['--format=json', '--no-error-on-unmatched-pattern'];
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr)
    {
        // Exit 1 can mean lint errors or a warning limit; other exits are failures.
        if ($err !== 0 && $err !== 1) {
            return false;
        }

        $report = json_decode($stdout, true);
        if (!is_array($report) || !is_array($report['diagnostics'] ?? null)) {
            return false;
        }

        if ($report['diagnostics'] !== array_values($report['diagnostics']) ||
            ($err && !$report['diagnostics'])) {
            return false;
        }

        $messages = [];
        foreach ($report['diagnostics'] as $diagnostic) {
            if (!is_array($diagnostic) ||
                !is_string($diagnostic['message'] ?? null) ||
                !in_array($diagnostic['severity'] ?? null, ['error', 'warning'], true)) {
                return false;
            }

            // Parser errors may have no rule code, and file-wide diagnostics no labels.
            $filename = $diagnostic['filename'] ?? $path;
            $code     = $diagnostic['code']     ?? 'OXLINT';
            $labels   = $diagnostic['labels']   ?? [];
            if (!is_string($filename) || !is_string($code) || !is_array($labels)) {
                return false;
            }

            if ($labels && !is_array($labels[0] ?? null)) {
                return false;
            }

            $span = $labels[0]['span'] ?? [];
            if (!is_array($span)) {
                return false;
            }

            $line   = $span['line']   ?? 1;
            $column = $span['column'] ?? 1;
            if (!is_int($line) || $line < 1 || !is_int($column) || $column < 1) {
                return false;
            }

            $message = new ArcanistLintMessage();
            $message->setPath($filename !== '' ? $filename : $path);
            $message->setLine($line);
            $message->setChar($column);
            $message->setCode($code);
            $message->setName($code);
            $message->setDescription($diagnostic['message']);
            $message->setSeverity(
                $diagnostic['severity'] === 'error'
                    ? ArcanistLintSeverity::SEVERITY_ERROR
                    : ArcanistLintSeverity::SEVERITY_WARNING
            );
            $messages[] = $message;
        }

        return $messages;
    }
}
