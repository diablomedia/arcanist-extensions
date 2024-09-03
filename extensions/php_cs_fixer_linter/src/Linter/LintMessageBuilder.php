<?php

use ptlis\DiffParser\Line;
use ptlis\DiffParser\Parser;

class LintMessageBuilder
{
    const CHANGE_NOTATION_REGEX = '#^(%s)(?:\s|[a-zA-Z]|$)#';

    /**
     * @var bool
     */
    private $guessMessages;

    public function __construct(bool $guessMessages = false)
    {
        $this->guessMessages = $guessMessages;
    }

    /**
     * @param string $path
     * @return \ArcanistLintMessage[]
     */
    public function buildLintMessages($path, array $fixData)
    {
        if ($this->guessMessages) {
            return $this->guessMessages($path, $fixData);
        }
        return $this->doBuildLintMessages($path, $fixData);
    }

    /**
     * @param int $line
     * @return ArcanistLintMessage
     */
    private function createLintMessage(string $path, array $diffPart, $line, array $fixData)
    {
        $message = $this->getPartialLintMessage($path, $line, $fixData['appliedFixers']);

        $description = [
            "Please consider applying these changes:\n```",
            "--- Original",
            "+++ New",
            "@@ @@",
        ];
        if (isset($diffPart['removals'])) {
            $removals = array_map(
                function ($item) {
                    return '- ' . trim(ltrim($item, '-'));
                },
                $diffPart['removals']
            );
            $description = array_merge($description, $removals);
        }
        if (isset($diffPart['additions'])) {
            $additions = array_map(
                function ($item) {
                    return '+ ' . trim(ltrim($item, '+'));
                },
                $diffPart['additions']
            );
            $description = array_merge($description, $additions);
        }
        $description[] = '```';

        $message->setDescription(implode("\n", $description));

        return $message;
    }

    private function doBuildLintMessages(string $path, array $fixData): array
    {
        $changeSet = (new Parser())->parseLines(explode("\n", $fixData['diff']));

        /** @var \ArcanistLintMessage[] $messages */
        $messages = [];

        $addedOffset = 0;
        foreach ($changeSet->getFiles() as $file) {
            foreach ($file->getHunks() as $hunk) {
                foreach ($hunk->getLines() as $line) {
                    if ($line->getOperation() === Line::UNCHANGED) {
                        continue;
                    }

                    $message = null;
                    if ($line->getOperation() === Line::ADDED) {
                        $lineNo = $line->getNewLineNo() - $addedOffset;
                    } else {
                        $lineNo = $line->getOriginalLineNo();
                    }

                    if (isset($messages[$lineNo])) {
                        $message = $messages[$lineNo];
                    }

                    if ($message === null) {
                        $message = new \ArcanistLintMessage();
                        $message->setName($this->getTrimmedAppliedFixers($fixData['appliedFixers']));
                        $message->setPath($path);
                        $message->setCode('php-cs-fixer');
                        $message->setSeverity(\ArcanistLintSeverity::SEVERITY_WARNING);
                        $message->setChar(1);
                        $message->setLine($lineNo);

                        if ($line->getOperation() === Line::ADDED) {
                            $addedOffset++;
                        }
                    }
                    if ($line->getOperation() === Line::ADDED) {
                        $message->setReplacementText($line->getContent());
                    }
                    if ($line->getOperation() === Line::REMOVED) {
                        $message->setOriginalText($line->getContent());
                    }
                    $messages[$message->getLine()] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param string $diff
     * @return array
     */
    private function extractDiffParts($diff)
    {
        $diffParts = [];
        $parts     = explode('@@ @@', $diff);
        array_shift($parts);
        $parts = array_values($parts);
        foreach ($parts as $key => $part) {
            $parts[$key] = array_map('trim', explode("\n", trim($part)));
        }

        $parts = $this->splitCombinedDiffs($parts);

        foreach ($parts as $key => $lines) {
            foreach ($lines as $line) {
                if ($this->isChangeNotationChar($line, '-')) {
                    $diffParts[$key]['removals'][] = $line;
                } elseif ($this->isChangeNotationChar($line, '+')) {
                    $diffParts[$key]['additions'][] = $line;
                } else {
                    $diffParts[$key]['informational'][] = $line;
                }
            }
        }

        $diffParts = array_filter($diffParts, function ($item) {
            if (
                isset($item['informational'])
                && (!isset($item['removals']) && !isset($item['additions']))
            ) {
                return false;
            }
            return true;
        });

        return $diffParts;
    }

    /**
     * @param string $path
     * @param int|null $line
     * @return ArcanistLintMessage
     */
    private function getPartialLintMessage($path, $line, array $appliedFixers)
    {
        $name = $this->getTrimmedAppliedFixers($appliedFixers);

        $message = new \ArcanistLintMessage();
        $message->setName($name);
        $message->setPath($path);
        $message->setCode('php-cs-fixer');
        $message->setLine($line);
        $message->setSeverity(\ArcanistLintSeverity::SEVERITY_WARNING);

        return $message;
    }

    private function getTrimmedAppliedFixers(array $appliedFixers): string
    {
        $fixers = implode(', ', $appliedFixers);
        if (strlen($fixers) > 255) {
            $fixers = substr($fixers, 0, 250) . '...';
        }

        return $fixers;
    }

    /**
     * @param string $path
     * @return \ArcanistLintMessage[]
     */
    private function guessMessages($path, array $fixData)
    {
        $diffParts = $this->extractDiffParts($fixData['diff']);
        $lines     = file($path);
        if ($lines === false) {
            return [];
        }
        $rows = array_map('trim', $lines);

        $messages = [];
        for ($i = 0; $i < count($rows); $i++) {
            foreach ($diffParts as $diffPart) {
                if (isset($diffPart['informational'])) {
                    $matchedInformational = 0;
                    foreach ($diffPart['informational'] as $key => $item) {
                        if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $item) {
                            break 2;
                        }
                        $matchedInformational++;
                    }
                    if ($matchedInformational === count($diffPart['informational'])) {
                        $i += $matchedInformational;
                        if (isset($diffPart['removals'])) {
                            $matchedRemovals = 0;
                            foreach ($diffPart['removals'] as $key => $removal) {
                                $realLine = $this->removeChangeNotationChar($removal, '-');
                                if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $realLine) {
                                    break 2;
                                }
                                $matchedRemovals++;
                            }
                            if ($matchedRemovals === count($diffPart['removals'])) {
                                $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                                $i += $matchedRemovals - 1;
                                array_shift($diffParts);
                                break 1;
                            }
                        } elseif (isset($diffPart['additions'])) {
                            $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                            $i--;
                            array_shift($diffParts);
                            break 1;
                        }
                    }
                } elseif (isset($diffPart['removals'])) {
                    $matchedRemovals = 0;
                    foreach ($diffPart['removals'] as $key => $removal) {
                        $realLine = $this->removeChangeNotationChar($removal, '-');
                        if (!isset($rows[$i + $key]) || $rows[$i + $key] !== $realLine) {
                            break 2;
                        }
                        $matchedRemovals++;
                    }
                    if ($matchedRemovals === count($diffPart['removals'])) {
                        $messages[] = $this->createLintMessage($path, $diffPart, $i + 1, $fixData);
                        $i += $matchedRemovals - 1;
                        array_shift($diffParts);
                        break 1;
                    }
                }
            }
        }

        if (count($diffParts) > 0) {
            $message = $this->getPartialLintMessage($path, null, $fixData['appliedFixers']);
            $message->setDescription(sprintf(
                "Lint engine was unable to extract exact line number\n"
                . "Please consider applying these changes:\n```%s```",
                $fixData['diff']
            ));

            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * @param string $string
     * @param string $char
     * @return bool
     */
    private function isChangeNotationChar($string, $char)
    {
        return preg_match(
            sprintf(self::CHANGE_NOTATION_REGEX, preg_quote($char, '#')),
            $string
        ) === 1;
    }

    /**
     * @param string $string
     * @param string $char
     * @return string
     */
    private function removeChangeNotationChar($string, $char)
    {
        return trim(preg_replace(
            sprintf(self::CHANGE_NOTATION_REGEX, preg_quote($char, '#')),
            '',
            $string
        ));
    }

    /**
     * @param int $index
     * @param int $position
     */
    private function spliceLines(array $lines, array &$parts, $index, $position): void
    {
        $part1 = array_slice($lines, 0, $position);
        $part2 = array_slice($lines, $position);
        array_splice($parts, $index, 1, [$part1, $part2]);
    }

    private function splitCombinedDiffs(array $parts): array
    {
        foreach ($parts as $key => $lines) {
            $removals       = 0;
            $lastRemovalNo  = 0;
            $additions      = 0;
            $lastAdditionNo = 0;
            foreach ($lines as $no => $line) {
                if ($this->isChangeNotationChar($line, '-')) {
                    $removals++;
                    $lastRemovalNo = $no + 1;
                } elseif ($this->isChangeNotationChar($line, '+')) {
                    $additions++;
                    $lastAdditionNo = $no + 1;
                } else {
                    if ($additions !== 0) {
                        $this->spliceLines($lines, $parts, $key, $lastAdditionNo);
                        return $this->splitCombinedDiffs($parts);
                    }
                    if ($removals !== 0) {
                        $this->spliceLines($lines, $parts, $key, $lastRemovalNo);
                        return $this->splitCombinedDiffs($parts);
                    }
                }
            }
        }

        return $parts;
    }
}
