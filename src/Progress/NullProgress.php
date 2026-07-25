<?php

declare(strict_types=1);

namespace DocbookCS\Progress;

final class NullProgress implements ProgressInterface
{
    public function start(int $totalFiles): void
    {
        // Intentionally left empty
    }

    public function advance(string $filePath, int $violations): void
    {
        // Intentionally left empty
    }

    public function finish(): void
    {
        // Intentionally left empty
    }
}
