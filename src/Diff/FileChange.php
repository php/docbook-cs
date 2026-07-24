<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final readonly class FileChange
{
    /**
     * @param list<int> $addedLineNumbers
     * @param list<int> $deletionAnchors
     */
    public function __construct(
        public string $filePath,
        public array $addedLineNumbers,
        public array $deletionAnchors = [],
    ) {
    }
}
