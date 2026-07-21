<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

final readonly class Diff
{
    /** @param list<FileChange> $fileChanges */
    public function __construct(public array $fileChanges)
    {
    }

    public function changeFor(string $filePath): ?FileChange
    {
        $normalisedPath = str_replace('\\', '/', $filePath);

        foreach ($this->fileChanges as $fileChange) {
            $normalisedDiffPath = str_replace('\\', '/', $fileChange->filePath);

            if (
                $normalisedPath === $normalisedDiffPath
                || str_ends_with($normalisedPath, '/' . ltrim($normalisedDiffPath, '/'))
            ) {
                return $fileChange;
            }
        }

        return null;
    }
}
