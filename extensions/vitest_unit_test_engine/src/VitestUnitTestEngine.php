<?php

final class VitestUnitTestEngine extends ArcanistUnitTestEngine
{
    const FORCE_ALL_FLAG = 'forceAll';

    /**
     * @var array
     */
    private $affectedTests = [];

    /**
     * @var string
     */
    private $command = '';

    /**
     * @var string
     */
    private $projectRoot;

    public function buildTestFuture(): ExecFuture
    {
        $config  = $this->getUnitConfigSection();
        $command = array_key_exists('bin', $config)
            ? "{$config['bin']} "
            : $this->getWorkingCopy()->getProjectRoot() . '/node_modules/.bin/vitest run --silent';

        if (true !== $config[self::FORCE_ALL_FLAG]) {
            $command .= implode(' ', array_unique($this->affectedTests));
        }

        // getEnableCoverage() returns either true, false, or null
        // true and false means it was explicitly turned on or off.  null means use the default
        if ($this->getEnableCoverage() !== false) {
            $command .= ' --reporter=json --coverage.reporter=json --coverage.enabled=true';
        }

        $this->command = $command;

        return new ExecFuture('%C', $command);
    }

    public function getEngineConfigurationName(): string
    {
        return 'vitest';
    }

    public function getIncludedFiles(string $include): array
    {
        $dir   = new RecursiveDirectoryIterator($this->projectRoot . $include);
        $ite   = new RecursiveIteratorIterator($dir);
        $files = new RegexIterator($ite, '/\.(js|ts|tsx|jsx)$/');

        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = $file->getRealPath();
        }

        return $fileList;
    }

    /**
     * @return null|array
     */
    public function getUnitConfigSection()
    {
        return $this->getConfigurationManager()->getConfigFromAnySource($this->getEngineConfigurationName());
    }

    public function getUnitConfigValue(string $name, mixed $default = null): mixed
    {
        $config = $this->getUnitConfigSection();
        return $config[$name] ?? $default;
    }

    public function parseTestResults(array $json_result): array
    {
        $results = [];

        if ($json_result['numTotalTests'] === 0 && $json_result['numTotalTestSuites'] === 0) {
            throw new ArcanistNoEffectException(pht('No tests to run.'));
        }

        foreach ($json_result['testResults'] as $test_result) {
            $duration_in_seconds = ($test_result['endTime'] - $test_result['startTime']) / 1000;
            $status_result       = $test_result['status'] === 'passed' ?
                ArcanistUnitTestResult::RESULT_PASS :
                ArcanistUnitTestResult::RESULT_FAIL;

            $extraData = [];
            foreach ($test_result['assertionResults'] as $assertion) {
                $extraData[] = $assertion['status'] === 'passed'
                    ? " [+] {$assertion['fullName']}"
                    : " [!] {$assertion['fullName']}";
            }

            $result = new ArcanistUnitTestResult();
            $result->setName($test_result['name']);
            $result->setResult($status_result);
            $result->setDuration($duration_in_seconds);
            $result->setUserData($test_result['message']);
            $result->setExtraData($extraData);
            $results[] = $result;
        }

        return $results;
    }

    public function run(): array
    {
        $this->projectRoot = $this->getWorkingCopy()->getProjectRoot();
        $include           = $this->getUnitConfigValue('include');
        $includeFiles      = $include !== null ? $this->getIncludedFiles($include) : [];
        $forceRunAll       = $this->getUnitConfigValue(self::FORCE_ALL_FLAG, false);

        if (!$forceRunAll) {
            foreach ($this->getPaths() as $path) {
                $path = Filesystem::resolvePath($path, $this->projectRoot);

                // TODO: add support for directories
                // Users can call phpunit on the directory themselves
                if (is_dir($path)) {
                    continue;
                }

                // Not sure if it would make sense to go further if it is not a JS file
                $extension = pathinfo($path, PATHINFO_EXTENSION);
                if (!in_array($extension, ['js', 'jsx', 'ts', 'tsx'])) {
                    continue;
                }

                // do we have an include pattern? does it match the file?
                if (null !== $include && !in_array($path, $includeFiles, true)) {
                    continue;
                }

                if (!Filesystem::pathExists($path)) {
                    continue;
                }

                $this->affectedTests[$path] = basename($path);
            }

            if (empty($this->affectedTests)) {
                throw new ArcanistNoEffectException(pht('No tests to run.'));
            }
        }

        $future                      = $this->buildTestFuture();
        list($err, $stdout, $stderr) = $future->resolve();

        // If we are running coverage the output includes a visual (non-JSON) representation
        // If that exists then exclude it before parsing the JSON.
        $json_start_index = strpos($stdout, '{"');
        $json_string      = substr($stdout, (int) $json_start_index);

        try {
            $json_result = phutil_json_decode($json_string);
        } catch (PhutilJSONParserException $ex) {
            $cmd = $this->command;
            throw new CommandException(
                pht(
                    "JSON command '%s' did not produce a valid JSON object on stdout: %s",
                    $cmd,
                    $stdout
                ),
                $cmd,
                0,
                $stdout,
                $stderr
            );
        }
        $test_results = $this->parseTestResults($json_result);

        // getEnableCoverage() returns either true, false, or null
        // true and false means it was explicitly turned on or off.  null means use the default
        if ($this->getEnableCoverage() !== false) {
            $coverage = $this->readCoverage($json_result);

            foreach ($test_results as $test_result) {
                $test_result->setCoverage($coverage);
            }
        }

        return $test_results;
    }

    public function shouldEchoTestResults(): bool
    {
        return false;
    }

    public function supportsRunAllTests(): bool
    {
        return true;
    }

    private function readCoverage(array $json_result): array
    {
        // vitest stores the coverage data in a separate file, need to read that
        // in and process that, which differs a little bit between the different
        // coverage providers.
        $coverageDir = $this->getUnitConfigValue('coverage.reportsDirectory', 'coverage');
        if (!$coverageDir) {
            return [];
        }

        $coverageDir = $this->projectRoot . DIRECTORY_SEPARATOR . $coverageDir;
        if (!is_dir($coverageDir)) {
            return [];
        }

        $coverageFile = $coverageDir . DIRECTORY_SEPARATOR . 'coverage-final.json';
        if (!file_exists($coverageFile)) {
            return [];
        }

        $contents = file_get_contents($coverageFile);

        if ($contents === false) {
            return [];
        }

        $json_result = json_decode($contents, true);

        switch ($this->getUnitConfigValue('coverage.provider')) {
            case 'istanbul':
                return $this->readIstanbulCoverage($json_result);
            case 'v8':
            default:
                return $this->readV8Coverage($json_result);
        }
    }

    private function readIstanbulCoverage(array $json_result): array
    {
        if (empty($json_result)) {
            return [];
        }

        $reports = [];
        foreach ($json_result as $fileCoverage) {
            $filePath   = $fileCoverage['path'];
            $shouldSkip = str_contains($filePath, '__fixtures__')
                || str_contains($filePath, '__mocks__')
                || str_contains($filePath, 'spec')
                || str_contains($filePath, '.test.');

            if ($shouldSkip) {
                continue;
            }

            $lines = file($filePath);
            if ($lines === false) {
                continue;
            }
            $lineCount = count($lines);
            /** @var string $fileName */
            $fileName           = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
            $reports[$fileName] = str_repeat('N', $lineCount);

            foreach ($fileCoverage['statementMap'] as $statementIndex => $chunk) {
                for ($i = $chunk['start']['line']; $i < $chunk['end']['line']; $i++) {
                    if ($fileCoverage['s'][$statementIndex] > 0) {
                        $reports[$fileName][$i] = 'C';
                    } elseif ($fileCoverage['s'][$statementIndex] == 0) {
                        $reports[$fileName][$i] = 'U';
                    }
                }
            }
        }

        return $reports;
    }

    private function readV8Coverage(array $json_result): array
    {
        if (empty($json_result)) {
            return [];
        }

        $reports = [];
        foreach ($json_result as $fileCoverage) {
            $filePath   = $fileCoverage['path'];
            $shouldSkip = str_contains($filePath, '__fixtures__')
                || str_contains($filePath, '__mocks__')
                || str_contains($filePath, 'spec')
                || str_contains($filePath, '.test.');

            if ($shouldSkip) {
                continue;
            }

            $lines = file($filePath);
            if ($lines === false) {
                continue;
            }
            $lineCount = count($lines);
            /** @var string $fileName */
            $fileName           = str_replace($this->projectRoot . DIRECTORY_SEPARATOR, '', $filePath);
            $reports[$fileName] = str_repeat('N', $lineCount); // not covered by default

            foreach ($fileCoverage['statementMap'] as $chunk) {
                $lineNum = $chunk['start']['line'];
                if ($fileCoverage['s'][($lineNum - 1)] > 0) {
                    $reports[$fileName][$lineNum - 1] = 'C';
                } elseif ($fileCoverage['s'][($lineNum - 1)] == 0) {
                    $reports[$fileName][$lineNum - 1] = 'U';
                }
            }
        }

        return $reports;
    }
}
