<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

use DocbookCS\Source\File;

final readonly class FixResult
{
    /** @param list<Fix> $appliedFixes */
    public function __construct(
        public File $file,
        public int $applied = 0,
        public int $skipped = 0,
        public array $appliedFixes = [],
    ) {
    }
}
