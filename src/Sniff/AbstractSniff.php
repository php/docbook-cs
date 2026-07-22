<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\RunMode;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;

abstract class AbstractSniff implements SniffInterface
{
    private const array NON_ELEMENT_DELIMITERS = [
        '<!--' => '-->',
        '<![CDATA[' => ']]>',
        '<?' => '?>',
    ];

    /** @var array<string, string> */
    protected array $properties = [];

    public function __construct(
        public RunMode $mode = RunMode::Sniff,
    ) {
    }

    public function setProperty(string $name, string $value): void
    {
        $this->properties[$name] = $value;
    }

    protected function getProperty(string $name, string $default = ''): string
    {
        return $this->properties[$name] ?? $default;
    }

    protected function isSourceBacked(\DOMNode $node): bool
    {
        return !EntityExpansionMarker::contains($node);
    }

    /**
     * @param list<SourceRange> $affectedRanges
     * @throws \LogicException if an invalid severity level is configured
     */
    protected function createViolation(
        string $filePath,
        int $line,
        int $beginOffset,
        int $untilOffset,
        string $message,
        ?string $content = null,
        Severity $severity = Severity::ERROR,
        array $affectedRanges = [],
    ): Violation {
        return new Violation(
            sniffCode: static::getCode(),
            filePath: $filePath,
            line: $line,
            beginOffset: $beginOffset,
            untilOffset: $untilOffset,
            message: $message,
            content: $content,
            severity: Severity::tryFrom($this->getProperty('severity', $severity->value))
                ?: throw new \LogicException('Invalid severity level configured for ExceptionNameSniff.'),
            affectedRanges: $affectedRanges,
        );
    }

    protected function maskNonElementMarkup(string $source): string
    {
        $masked = $source;
        $offset = 0;

        while (false !== $start = strpos($source, '<', $offset)) {
            $endOffset = $this->nonElementMarkupEndOffset($source, $start);

            if ($endOffset === null) {
                $offset = $start + 1;
                continue;
            }

            for ($i = $start; $i < $endOffset; $i++) {
                $masked[$i] = ' ';
            }

            $offset = $endOffset;
        }

        return $masked;
    }

    private function nonElementMarkupEndOffset(string $source, int $start): ?int
    {
        foreach (self::NON_ELEMENT_DELIMITERS as $opening => $closing) {
            if (substr_compare($source, $opening, $start, strlen($opening)) === 0) {
                return $this->offsetAfterDelimiter($source, $closing, $start);
            }
        }

        if (substr_compare($source, '<!', $start, 2) === 0) {
            return $this->declarationEndOffset($source, $start);
        }

        return null;
    }

    private function offsetAfterDelimiter(string $source, string $delimiter, int $offset): int
    {
        $end = strpos($source, $delimiter, $offset);

        return $end === false ? strlen($source) : $end + strlen($delimiter);
    }

    private function declarationEndOffset(string $source, int $offset): int
    {
        $length = strlen($source);
        $quote = null;
        $bracketDepth = 0;

        for ($i = $offset; $i < $length; $i++) {
            $character = $source[$i];

            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                continue;
            }

            if ($character === '[') {
                $bracketDepth++;
                continue;
            }

            if ($character === ']') {
                $bracketDepth--;
                continue;
            }

            if ($character === '>' && $bracketDepth === 0) {
                return $i + 1;
            }
        }

        return $length;
    }
}
