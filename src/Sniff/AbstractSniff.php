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
}
