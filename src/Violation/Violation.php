<?php

declare(strict_types=1);

namespace DocbookCS\Violation;

final readonly class Violation
{
    /** @var non-empty-list<SourceRange> */
    public array $affectedRanges;

    /**
     * @param list<SourceRange> $affectedRanges
     */
    public function __construct(
        public string $sniffCode,
        public string $filePath,
        public int $line,
        public int $beginOffset,
        public int $untilOffset,
        public string $message,
        public ?string $content = null,
        public Severity $severity = Severity::WARNING,
        array $affectedRanges = [],
    ) {
        $this->affectedRanges = $affectedRanges !== []
            ? array_values($affectedRanges)
            : [new SourceRange($line, $beginOffset, $untilOffset)];
    }
}
