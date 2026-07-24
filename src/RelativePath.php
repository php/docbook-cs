<?php

declare(strict_types=1);

namespace DocbookCS;

final class RelativePath
{
    public static function fromWorkingDirectory(string $filePath): string
    {
        $workingDirectory = getcwd();
        if ($workingDirectory === false) {
            return $filePath; // @codeCoverageIgnore
        }

        $prefix = rtrim(str_replace('\\', '/', $workingDirectory), '/') . '/';
        $normalisedPath = str_replace('\\', '/', $filePath);

        return str_starts_with($normalisedPath, $prefix)
            ? substr($normalisedPath, strlen($prefix))
            : $filePath;
    }
}
