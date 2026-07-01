<?php

declare(strict_types=1);

namespace DocbookCS\Path;

final class EntityResolver
{
    private string $extension;

    /**
     * @param array<string, string> $projectRoots
     * @param list<string> $entityPaths
     */
    public function __construct(
        private readonly array $projectRoots,
        private readonly array $entityPaths,
        string $extension = 'ent'
    ) {
        $this->extension = ltrim($extension, '.');
    }

    /**
     * @return array<string, string>
     * @throws \UnexpectedValueException if the directory cannot be read.
     */
    public function resolve(): array
    {
        $entities = [];

        foreach ($this->entityPaths as $path) {
            foreach ($this->getEntityFiles($path) as $file) {
                $entities += $this->resolveFile($file);
            }
        }

        return $entities;
    }

    /**
     * @return list<string>
     * @throws \UnexpectedValueException if the directory cannot be read.
     */
    private function getEntityFiles(string $path): array
    {
        if (is_file($path) && $this->isEntityFile($path)) {
            return [$path];
        }

        if (!is_dir($path)) {
            return [];
        }

        return $this->scanDirectory($path);
    }

    private function isEntityFile(string $path): bool
    {
        return str_ends_with($path, '.' . $this->extension);
    }

    /**
     * @return list<string>
     * @throws \UnexpectedValueException if the directory cannot be read.
     */
    private function scanDirectory(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
            )
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile() && $this->isEntityFile($fileInfo->getPathname())) {
                $files[] = str_replace('\\', '/', $fileInfo->getPathname());
            }
        }

        return $files;
    }

    /**
     * @param array<string, bool> $visited
     * @return array<string, string>
     */
    private function resolveFile(
        string $filePath,
        array &$visited = [],
        ?string $originEntity = null
    ): array {
        if (isset($visited[$filePath]) || !is_readable($filePath)) {
            return [];
        }

        $visited[$filePath] = true;

        $content = file_get_contents($filePath);

        if ($content === false) {
            return []; // @codeCoverageIgnore
        }

        $entities = $this->extractEntities($content, $filePath, $visited);

        if ($originEntity !== null) {
            $entities[$originEntity] = $this->normalize($content);
        }

        return $entities;
    }

    /**
     * @param array<string, bool> $visited
     * @return array<string, string>
     */
    private function extractEntities(
        string $content,
        string $filePath,
        array &$visited
    ): array {
        return
            $this->extractDtdEntities($content, $filePath, $visited)
            + $this->extractXmlEntities($content);
    }

    /**
     * @param array<string, bool> $visited
     * @return array<string, string>
     */
    private function extractDtdEntities(
        string $content,
        string $filePath,
        array &$visited
    ): array {
        $result = [];

        if (!str_contains($content, '<!ENTITY')) {
            return $result;
        }

        if (
            !preg_match_all(
                '/<!ENTITY\s+(?:%\s*)?([A-Za-z0-9_\-:.]+)\s+(?:(SYSTEM)\s+)?(["\'])([\s\S]*?)\3\s*>/',
                $content,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            return $result;
        }

        foreach ($matches as $match) {
            $name = $match[1];
            $type = $match[2] ?: null;
            $value = trim($match[4]);

            if ($type === 'SYSTEM') {
                $resolvedPath = $this->resolvePath($filePath, $value);
                $result += $this->resolveFile($resolvedPath, $visited, $name);

                continue;
            }

            $result[$name] = $this->normalize($value);
        }

        return $result;
    }

    /** @return array<string, string> */
    private function extractXmlEntities(string $content): array
    {
        $result = [];

        if (!str_contains($content, '<entity')) {
            return $result;
        }

        if (
            !preg_match_all(
                '/<entity\s+name\s*=\s*(["\'])([A-Za-z0-9_\-:.]+)\1\s*(?:\/>|>([\s\S]*?)<\/entity\s*>)/',
                $content,
                $matches,
                PREG_SET_ORDER
            )
        ) {
            return $result;
        }

        foreach ($matches as $match) {
            $name = $match[2];
            $value = $match[3] ?? '';

            $result[$name] = $this->normalize($value);
        }

        return $result;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: $value;
    }

    private function resolvePath(string $path, string $reference): string
    {
        if (str_starts_with($reference, '/') && is_file($reference)) {
            return $reference;
        }

        foreach ($this->projectRoots as $root => $directory) {
            if (!str_contains($reference, '/' . $directory . '/')) {
                continue;
            }

            [$prefix] = explode('/' . $directory . '/', $reference);
            $reference = str_replace($prefix . '/' . $directory, $root, $reference);
        }

        if (str_starts_with($reference, '/')) {
            return $reference;
        }

        return dirname($path) . DIRECTORY_SEPARATOR . $reference;
    }
}
