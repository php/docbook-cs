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
}
