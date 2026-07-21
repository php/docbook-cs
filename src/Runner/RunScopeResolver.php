<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\FileChange;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;

final readonly class RunScopeResolver
{
    private const string ENTITY_PATTERN = '/&([a-zA-Z_][\w.\-]*);/';

    private PathMatcher $pathMatcher;

    /** @param array<string, string> $entityPaths */
    public function __construct(
        private ConfigData $config,
        private array $entityPaths,
        private bool $wide = false,
    ) {
        $this->pathMatcher = new PathMatcher(
            $config->getBasePath(),
            $config->getExcludePatterns(),
        );
    }

    /**
     * @param list<string> $paths
     * @return array<string, FileChange|null>
     * @throws \UnexpectedValueException if a selected directory cannot be read.
     */
    public function resolvePaths(array $paths): array
    {
        $targets = [];

        foreach (new PathLoader($this->absolutePaths($paths), $this->pathMatcher)->loadPaths() as $file) {
            $targets[$file] = null;
        }

        return $this->finalize($targets);
    }

    /** @return array<string, FileChange|null> */
    public function resolveDiff(Diff $diff): array
    {
        $resolvedDiff = new DiffPathLoader(
            $diff,
            getcwd() ?: '.',
            $this->config->getBasePath(),
            $this->config->getProjectRoots(),
            $this->pathMatcher,
        )->load();

        $targets = [];

        foreach ($resolvedDiff->fileChanges as $fileChange) {
            $targets[$fileChange->filePath] = $fileChange;
        }

        return $this->finalize($targets);
    }

    /**
     * @param array<string, FileChange|null> $targets
     * @return array<string, FileChange|null>
     */
    private function finalize(array $targets): array
    {
        if ($this->wide) {
            $targets = array_fill_keys(array_keys($targets), null);
            $this->expandReferencedTargets($targets);
        }

        ksort($targets);

        return $targets;
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function absolutePaths(array $paths): array
    {
        $workingDirectory = getcwd() ?: '.';
        $absolutePaths = [];

        foreach ($paths as $path) {
            $absolutePath = str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:[/\\\\]#', $path)
                ? $path
                : $workingDirectory . '/' . $path;

            $absolutePaths[] = $this->normalizePath($absolutePath);
        }

        return $absolutePaths;
    }

    /** @param array<string, FileChange|null> $targets */
    private function expandReferencedTargets(array &$targets): void
    {
        $pending = array_keys($targets);
        $visitedFiles = [];
        $visitedEntityPaths = [];

        for ($i = 0; isset($pending[$i]); $i++) {
            $file = $pending[$i];

            if (isset($visitedFiles[$file])) {
                continue;
            }

            $visitedFiles[$file] = true;
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            foreach ($this->targetFilesFromContent($content, $visitedEntityPaths) as $targetFile) {
                if (array_key_exists($targetFile, $targets)) {
                    continue;
                }

                $targets[$targetFile] = null;
                $pending[] = $targetFile;
            }
        }
    }

    /**
     * @param array<string, true> $visitedEntityPaths
     * @return list<string>
     */
    private function targetFilesFromContent(string $content, array &$visitedEntityPaths): array
    {
        if (!preg_match_all(self::ENTITY_PATTERN, $content, $matches)) {
            return [];
        }

        $files = [];

        foreach ($matches[1] as $name) {
            if (!isset($this->entityPaths[$name])) {
                continue;
            }

            foreach ($this->expandEntityPath($this->entityPaths[$name], $visitedEntityPaths) as $file) {
                $files[$file] = true;
            }
        }

        return array_keys($files);
    }

    /**
     * @param array<string, true> $visited
     * @return list<string>
     */
    private function expandEntityPath(string $path, array &$visited): array
    {
        $path = $this->normalizePath($path);

        if (isset($visited[$path]) || !is_file($path)) {
            return [];
        }

        $visited[$path] = true;

        if (str_ends_with($path, '.xml')) {
            return $this->pathMatcher->isIncluded($path) ? [$path] : [];
        }

        $content = @file_get_contents($path);

        return $content !== false
            ? $this->targetFilesFromContent($content, $visited)
            : [];
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('#^([a-zA-Z]:/|/)#', $path, $matches)) {
            $prefix = $matches[1];
            $path = substr($path, strlen($prefix));
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
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
