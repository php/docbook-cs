<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Source\File;
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

    protected Severity $severity = Severity::ERROR;

    /** @var array<string, string> */
    protected array $properties = [];

    /** @throws \InvalidArgumentException if a configured severity is invalid */
    public function setProperty(string $name, string $value): void
    {
        if ($name !== 'severity') {
            $this->properties[$name] = $value;
            return;
        }

        if (null !== $severity = Severity::tryFrom($value)) {
            $this->severity = $severity;
            return;
        }

        throw new \InvalidArgumentException(sprintf('Invalid severity "%s" config for %s.', $value, static::getCode()));
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
     * The offsets point at the opening "<" and closing "<" in the source.
     *
     * @return array{SourceRange, SourceRange}
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a tag offset lies outside the source
     */
    protected function elementNameRanges(File $file, int $beginOffset, int $untilOffset, string $elementName): array
    {
        $openingNameOffset = $beginOffset + 1;
        $closingNameOffset = $untilOffset + 2;
        $elementNameLength = strlen($elementName);

        return [
            SourceRange::fromFile(
                $file,
                $openingNameOffset,
                $openingNameOffset + $elementNameLength,
            ),
            SourceRange::fromFile(
                $file,
                $closingNameOffset,
                $closingNameOffset + $elementNameLength,
            ),
        ];
    }

    /**
     * @param non-empty-list<SourceRange> $affectedRanges
     *
     * @throws \InvalidArgumentException if the affected ranges are inconsistent
     */
    protected function createViolation(string $filePath, string $message, array $affectedRanges): Violation
    {
        return new Violation(
            sniffCode: static::getCode(),
            filePath: $filePath,
            message: $message,
            affectedRanges: $affectedRanges,
            severity: $this->severity,
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
