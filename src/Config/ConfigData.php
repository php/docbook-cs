<?php

declare(strict_types=1);

namespace DocbookCS\Config;

final readonly class ConfigData
{
    /**
     * @param array<string, string> $projectRoots
     * @param list<SniffEntry> $sniffs
     * @param list<string> $includePaths
     * @param list<string> $excludePatterns
     * @param list<string> $entityPaths
     * @param string $basePath
     */
    public function __construct(
        private array $projectRoots,
        private array $sniffs,
        private array $includePaths,
        private array $excludePatterns,
        private array $entityPaths,
        private string $basePath,
    ) {}

    /** @return array<string, string> */
    public function getProjectRoots(): array
    {
        return $this->projectRoots;
    }

    /** @return list<SniffEntry> */
    public function getSniffs(): array
    {
        return $this->sniffs;
    }

    /** @return list<string> */
    public function getIncludePaths(): array
    {
        return $this->includePaths;
    }

    /** @return list<string> */
    public function getExcludePatterns(): array
    {
        return $this->excludePatterns;
    }

    /** @return list<string> */
    public function getEntityPaths(): array
    {
        return $this->entityPaths;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
