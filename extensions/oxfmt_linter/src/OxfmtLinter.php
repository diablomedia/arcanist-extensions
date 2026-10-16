<?php

final class OxfmtLinter extends ArcanistExternalLinter
{
    public function getDefaultBinary()
    {
        return $this->getProjectRoot() . '/node_modules/.bin/oxfmt';
    }

    public function getInfoName()
    {
        return 'Oxfmt';
    }

    public function getInfoURI()
    {
        return 'https://oxc.rs/docs/guide/usage/formatter.html';
    }

    public function getInstallInstructions()
    {
        return 'Install oxfmt in your project with `npm install --save-dev oxfmt` ' .
            '(or the equivalent for your package manager), or configure "bin" in .arclint.';
    }

    public function getLinterConfigurationName(): string
    {
        return 'oxfmt';
    }

    public function getLinterName(): string
    {
        return 'OXFMT';
    }

    protected function buildFutures(array $paths): array
    {
        if (!$paths) {
            return [];
        }

        $executable = $this->getExecutableCommand();
        $flags      = $this->getCommandFlags();
        $futures    = [];
        foreach ($paths as $path) {
            $future = new ExecFuture('%C %Ls --stdin-filepath %s', $executable, $flags, $path);
            $future->setCWD($this->getProjectRoot());
            $future->write($this->getData($path));
            $futures[$path] = $future;
        }

        return $futures;
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr)
    {
        if ($err) {
            return false;
        }

        $original = $this->getData($path);
        if ($original === $stdout) {
            return [];
        }

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine(1);
        $message->setChar(1);
        $message->setCode('OXFMT');
        $message->setName('Oxfmt Format');
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX);
        $message->setDescription('Apply this patch or run oxfmt on this file to format it.');
        $message->setOriginalText($original);
        $message->setReplacementText($stdout);
        $message->setBypassChangedLineFiltering(true);

        return [$message];
    }
}
