<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

final readonly class Fix
{
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
