<?php

/**
 * Copyright 2016 Pinterest, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Lints JavaScript and JSX files using ESLint
 */
final class ESLintBatchLinter extends ArcanistExternalLinter
{
    const ESLINT_ERROR = '2';

    const ESLINT_WARNING = '1';

    /**
     * @var string
     */
    protected $cwd = '';

    /**
     * @var string Eslint binary to execute (optionally provided via config)
     */
    protected $eslintBin;

    /**
     * @var array
     */
    private $flags = [];

    /**
     * @var bool Whether to parse and apply fixes suggested by eslint
     */
    private $parseFixes = false;

    public function getDefaultBinary()
    {
        return $this->resolveBinaryPath(
            $this->getNodeBinary(),
            $this->getNodeCwd()
        );
    }

    public function getInfoDescription()
    {
        return pht('The pluggable linting utility for JavaScript and JSX');
    }

    public function getInfoName()
    {
        return 'ESLint';
    }

    public function getInfoURI()
    {
        return 'https://eslint.org/';
    }

    public function getInstallInstructions()
    {
        return pht(
            "\n\t%s[%s globally] run: `%s`\n\t[%s locally] run either: `%s` OR `%s`",
            $this->cwd ? pht(
                "[%s globally] (required for %s) run: `%s`\n\t",
                'yarn',
                '--cwd',
                'npm install --global yarn@1'
            ) : '',
            $this->getNodeBinary(),
            'npm install --global ' . $this->getNpmPackageName(),
            $this->getNodeBinary(),
            'npm install --save-dev ' . $this->getNpmPackageName(),
            'yarn add --dev ' . $this->getNpmPackageName()
        );
    }

    /**
     * @return string The linter name to be used in .arclint
     */
    public function getLinterConfigurationName()
    {
        return 'eslint-batch';
    }

    /**
     * @return array
     */
    public function getLinterConfigurationOptions()
    {
        $options = [
          'eslint.config' => [
            'type' => 'optional string',
            'help' => pht('Use configuration from this file or shareable config. (https://eslint.org/docs/user-guide/command-line-interface#-c---config)'),
          ],
          'eslint.env' => [
            'type' => 'optional string',
            'help' => pht('Specify environments. To specify multiple environments, separate them using commas. (https://eslint.org/docs/user-guide/command-line-interface#--env)'),
          ],
          'eslint.fix' => [
            'type' => 'optional bool',
            'help' => pht('Specify whether to patch eslint provided autofixes. (https://eslint.org/docs/user-guide/command-line-interface#fixing-problems)'),
          ],
        ];
        return $options + parent::getLinterConfigurationOptions();
    }

    /**
    * @return string
    */
    public function getLinterName()
    {
        return 'ESLINT';
    }

    /**
     * @return string
     */
    public function getNodeBinary()
    {
        return 'eslint';
    }

    /**
     * @return string
     */
    public function getNodeCwd()
    {
        if ($this->cwd) {
            return $this->cwd;
        }
        return $this->getProjectRoot();
    }

    /**
     * Return a human-readable string describing how to upgrade the linter.
     *
     * @return string Human readable upgrade instructions
     * @task bin
     */
    public function getUpgradeInstructions()
    {
        return $this->getInstallInstructions();
    }

    /**
     * @return string|false
     */
    public function getVersion()
    {
        list($err, $stdout, $stderr) = exec_manual('%C -v', $this->getExecutableCommand());

        $matches = [];
        if (preg_match('/^v(\d\.\d\.\d)$/', $stdout, $matches)) {
            return $matches[1];
        } else {
            return false;
        }
    }

    /**
     * @param mixed $key
     * @param mixed $value
     * @return void|null
     */
    public function setLinterConfigurationValue($key, $value)
    {
        switch ($key) {
            case 'eslint.config':
                $this->flags[] = '--config';
                $this->flags[] = $value;
                return;
            case 'eslint.env':
                $this->flags[] = '--env';
                $this->flags[] = $value;
                return;
            case 'eslint.fix':
                if ($value) {
                    $this->parseFixes = true;
                }
                return;
            case 'bin':
                $root = $this->getProjectRoot();
                foreach ((array)$value as $path) {
                    if (Filesystem::binaryExists($path)) {
                        $this->setBinary($path);
                        $this->eslintBin = $path;
                        return;
                    }
                    $path = Filesystem::resolvePath($path, $root);
                    if (Filesystem::binaryExists($path)) {
                        $this->setBinary($path);
                        $this->eslintBin = $path;
                        return;
                    }
                }
                throw new Exception(
                    pht('None of the configured binaries can be located.')
                );
        }
        return parent::setLinterConfigurationValue($key, $value);
    }

    public function willLintPaths(array $paths): void
    {
        $flags = $this->getCommandFlags();

        $bin = csprintf('%s', $this->eslintBin);
        $bin = csprintf('%C %Ls', $bin, $flags);

        $future = new ExecFuture('%C %C', $bin, implode(' ', $paths));
        $future->setCWD($this->getProjectRoot());

        list($err, $stdout, $stderr) = $future->resolve();

        $messages = $this->parseLinterOutput('', $err, $stdout, $stderr);

        if ($err && empty($messages)) {
            throw new Exception(
                sprintf(
                    "%s\n\nSTDOUT\n%s\n\nSTDERR\n%s",
                    pht('Linter failed to parse output!'),
                    $stdout,
                    $stderr
                )
            );
        }

        if ($messages) {
            foreach ($messages as $message) {
                $this->addLintMessage($message);
            }
        }
    }

    /**
     * @return false
     */
    protected function canCustomizeLintSeverities()
    {
        return false;
    }

    protected function getDefaultFlags()
    {
        if ($this->cwd) {
            $this->flags[] = '--resolve-plugins-relative-to';
            $this->flags[] = $this->cwd;
        }
        return $this->flags;
    }

    protected function getMandatoryFlags()
    {
        return [
          '--format=json',
          '--no-color',
        ];
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr)
    {
        // Gate on $stderr b/c $err (exit code) is expected.
        if ($stderr) {
            return false;
        }

        $json     = json_decode($stdout, true);
        $messages = [];

        foreach ($json as $file) {
            foreach ($file['messages'] as $offense) {
                // Skip file ignored warning: if a file is ignored by .eslintingore
                // but linted explicitly (by arcanist), a warning will be reported,
                // containing only: `{fatal:false,severity:1,message:...}`.
                if (str_starts_with($offense['message'], "File ignored ")) {
                    continue;
                }

                /**
                 * Example ESLint message:
                 * {
                 *     "ruleId": "prettier/prettier",
                 *     "severity": 2,
                 *     "message": "Replace `(flow.component·&&·flow.component.archived)` \
                 *         with `flow.component·&&·flow.component.archived`",
                 *     "line": 61,
                 *     "column": 10,
                 *     "nodeType": null,
                 *     "messageId": "replace",
                 *     "endLine": 61,
                 *     "endColumn": 53,
                 *     "fix": {
                 *         "range": [
                 *             1462,
                 *             1505
                 *         ],
                 *         "text": "flow.component && flow.component.archived"
                 *     }
                 * },
                 */

                $message = new ArcanistLintMessage();
                $message->setPath($file['filePath']);
                $message->setName(nonempty(idx($offense, 'ruleId'), 'unknown'));
                $message->setDescription(idx($offense, 'message'));
                $message->setLine(idx($offense, 'line'));
                $message->setChar(idx($offense, 'column'));
                $message->setCode($this->getLinterName());

                if ($this->parseFixes && isset($offense['fix'])) {
                    $fix = $offense['fix'];
                    // If there's a fix available, suggest it to the user.
                    // We don't want to rely on the --fix flag for eslint because it will
                    // silently fix, and then arc won't know it should patch new changes
                    // into the commit.
                    $range        = $fix['range'];
                    $rangeStart   = $range[0];
                    $rangeEnd     = $range[1];
                    $rangeLength  = $rangeEnd - $rangeStart;
                    $originalText = $this->getData($file['filePath']);

                    // Make sure to always use multibyte safe string ranges.
                    $originalSlice = mb_substr($originalText, $rangeStart, $rangeLength);
                    $message->setOriginalText($originalSlice);

                    $replacementSlice = $fix['text'];
                    $message->setReplacementText($replacementSlice);
                    $message->setSeverity(ArcanistLintSeverity::SEVERITY_AUTOFIX);
                } else {
                    $message->setSeverity($this->mapSeverity((string) idx($offense, 'severity', '0')));
                }

                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param string $bin
     * @param string $root
     * @return string
     */
    protected function resolveBinaryPath($bin, $root)
    {
        // Yarn will tell us where the binary is, try that first
        list($err, $stdout, $stderr) = exec_manual('yarn -s --cwd %s bin %s', $root, $bin);
        if ($stdout) {
            return strtok($stdout, "\n");
        }

        // Ask npm for the location of its bin directory
        list($err, $stdout, $stderr) = exec_manual('npm bin');
        if ($stdout) {
            $path = Filesystem::resolvePath(strtok($stdout, "\n"));
        } else {
            // Assume the path is in the standard location
            $modulesPath = Filesystem::resolvePath('node_modules', $root);
            if (is_dir($modulesPath . DIRECTORY_SEPARATOR . '.bin')) {
                $path = $modulesPath . DIRECTORY_SEPARATOR . '.bin';
            }
        }

        if (isset($path) && $path) {
            $binaryPath = $path . DIRECTORY_SEPARATOR . $bin;
            if (Filesystem::binaryExists($binaryPath)) {
                return $binaryPath;
            }
        }

        // Fall back to global binary in $PATH
        return $bin;
    }

    /**
     * @return string
     */
    private function getNpmPackageName()
    {
        return $this->getNodeBinary();
    }

    /**
     * @param int|string $eslintSeverity
     * @return string
     */
    private function mapSeverity($eslintSeverity)
    {
        switch ($eslintSeverity) {
            case '0':
            case '1':
                return ArcanistLintSeverity::SEVERITY_WARNING;
            case '2':
            default:
                return ArcanistLintSeverity::SEVERITY_ERROR;
        }
    }
}
