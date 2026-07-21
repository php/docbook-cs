<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final readonly class FileChange
{
    /** @param list<int> $addedLineNumbers */
    public function __construct(
        public string $filePath,
        public array $addedLineNumbers,
    ) {
    }
}
