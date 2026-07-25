<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;

final readonly class Fix
{
    public static function fromViolationAndRange(Violation $violation, SourceRange $range, string $replacement): self
    {
        return new self(
            filePath: $violation->filePath,
            beginOffset: $range->beginOffset,
            untilOffset: $range->untilOffset,
            replacement: $replacement,
            sniffCode: $violation->sniffCode,
            expectedContent: $range->content,
        );
    }

    public function __construct(
        public string $filePath,
        public int $beginOffset,
        public int $untilOffset,
        public string $replacement,
        public string $sniffCode,
        public ?string $expectedContent = null,
    ) {
    }
}
