<?php

declare(strict_types=1);

namespace DocbookCS\Path;

use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\FileChange;

final readonly class DiffPathLoader
{
    /**
     * @param array<string, string> $projectRoots
     */
    public function __construct(
        private DiffChangeset $diff,
        private string $workingDirectory,
        private string $basePath,
        private array $projectRoots,
        private PathMatcher $matcher,
    ) {
    }

    public function load(): DiffChangeset
    {
        $changes = [];

        foreach ($this->diff->fileChanges as $fileChange) {
            foreach ($this->candidates($fileChange->filePath) as $candidate) {
                if (
                    is_file($candidate)
                    && str_ends_with(strtolower($candidate), '.xml')
                    && $this->matcher->isIncluded($candidate)
                ) {
                    $changes[$candidate] = new FileChange(
                        $candidate,
                        $fileChange->addedLineNumbers,
                        $fileChange->deletionAnchors,
                    );
                    break;
                }
            }
        }

        ksort($changes);

        return new DiffChangeset(array_values($changes));
    }

    /** @return list<string> */
    private function candidates(string $path): array
    {
        $path = str_replace('\\', '/', $path);

        if ($this->isAbsolute($path)) {
            return [$path];
        }

        $candidates = [
            $this->workingDirectory . '/' . $path,
            $this->basePath . '/' . $path,
        ];

        foreach ($this->projectRoots as $root => $directory) {
            $candidates[] = $root . '/' . $path;

            $prefix = trim(str_replace('\\', '/', $directory), '/') . '/';
            if ($prefix !== '/' && str_starts_with($path, $prefix)) {
                $candidates[] = $root . '/' . substr($path, strlen($prefix));
            }
        }

        return array_map($this->normalize(...), $candidates)
                |> array_unique(...)
                |> array_values(...);
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:/#', $path) === 1;
    }

    private function normalize(string $path): string
    {
        $prefix = str_starts_with($path, '/') ? '/' : '';
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return $prefix . implode('/', $segments);
    }
}
