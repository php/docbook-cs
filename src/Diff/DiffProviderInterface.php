<?php

declare(strict_types=1);

namespace DocbookCS\Diff;

interface DiffProviderInterface
{
    public function for(string $workingDirectory): string;
}
