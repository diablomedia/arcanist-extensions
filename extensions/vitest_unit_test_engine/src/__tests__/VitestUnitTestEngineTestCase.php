<?php

final class VitestUnitTestEngineTestCase extends PhutilTestCase
{
    public function testReadV8CoverageIgnoresMissingStatementCounters(): void
    {
        $this->assertEqual(
            'N',
            $this->readCoverage(
                [
                    13 => $this->statement(1),
                ],
                [],
                1
            )
        );
    }

    public function testReadV8CoveragePreservesCoveredLine(): void
    {
        $this->assertEqual(
            'CN',
            $this->readCoverage(
                [
                    3 => $this->statement(1),
                    8 => $this->statement(1),
                ],
                [
                    3 => 1,
                    8 => 0,
                ],
                2
            )
        );
    }

    public function testReadV8CoverageReportsZeroCountsAsUncovered(): void
    {
        $this->assertEqual(
            'NU',
            $this->readCoverage(
                [
                    4 => $this->statement(2),
                ],
                [
                    4 => 0,
                ],
                2
            )
        );
    }

    public function testReadV8CoverageUsesStatementIndexes(): void
    {
        $this->assertEqual(
            'CUN',
            $this->readCoverage(
                [
                    7  => $this->statement(1),
                    12 => $this->statement(2),
                ],
                [
                    7  => 1,
                    12 => 0,
                ]
            )
        );
    }

    private function readCoverage(
        array $statementMap,
        array $statementCounts,
        int $lineCount = 3
    ): string {
        $projectRoot = Filesystem::createTemporaryDirectory('vitest-coverage');
        $filePath    = $projectRoot . DIRECTORY_SEPARATOR . 'example.ts';
        Filesystem::writeFile($filePath, str_repeat("test();\n", $lineCount));

        try {
            $engine  = new VitestUnitTestEngine();
            $root    = new ReflectionProperty($engine, 'projectRoot');
            $reader  = new ReflectionMethod($engine, 'readV8Coverage');
            $payload = [
                $filePath => [
                    'path'         => $filePath,
                    'statementMap' => $statementMap,
                    's'            => $statementCounts,
                ],
            ];

            $root->setAccessible(true);
            $reader->setAccessible(true);
            $root->setValue($engine, $projectRoot);

            $reports = $reader->invoke($engine, $payload);

            return $reports['example.ts'];
        } finally {
            Filesystem::remove($projectRoot);
        }
    }

    private function statement(int $line): array
    {
        return [
            'start' => [
                'line'   => $line,
                'column' => 0,
            ],
            'end' => [
                'line'   => $line,
                'column' => 1,
            ],
        ];
    }
}
