<?php

declare(strict_types=1);

namespace DocbookCS\Path;

final class PathNormalizer
{
    public static function isAbsolute(string $path): bool
    {
        return preg_match('#^([a-zA-Z]:)?[/\\\\]#', $path) === 1;
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = '';

        if (preg_match('#^(//|[a-zA-Z]:/|/)#', $path, $matches)) {
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
