<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\FileChange;

final readonly class RunPlan
{
    /**
     * @param list<SniffEntry> $sniffs
     * @param array<string, string> $entities
     * @param array<string, FileChange|null> $targets
     */
    public function __construct(
        public RunMode $mode,
        public array $sniffs,
        public array $targets,
        public array $entities,
    ) {
    }
}
