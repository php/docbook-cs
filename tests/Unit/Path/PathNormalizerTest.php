<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Path\PathNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(PathNormalizer::class),
]
final class PathNormalizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function normalizedPaths(): iterable
    {
        yield 'unix path' => ['/var/tmp/file.xml', '/var/tmp/file.xml'];
        yield 'relative path' => ['en/reference/file.xml', 'en/reference/file.xml'];
        yield 'windows backslashes' => ['C:\\Users\\RUNNER~1\\Temp\\file.xml', 'C:/Users/RUNNER~1/Temp/file.xml'];
        yield 'mixed separators' => ['D:\\a\\docbook-cs/tests/file.xml', 'D:/a/docbook-cs/tests/file.xml'];
        yield 'unc path' => ['\\\\server\\share\\file.xml', '//server/share/file.xml'];
        yield 'dot segments' => ['/var/./tmp//file.xml', '/var/tmp/file.xml'];
        yield 'parent segments' => ['/var/nested/../tmp/file.xml', '/var/tmp/file.xml'];
        yield 'parent segments on a drive' => ['C:\\a\\b\\..\\..\\file.xml', 'C:/file.xml'];
        yield 'leading parent segments are kept' => ['../../file.xml', '../../file.xml'];
        yield 'parent segments above the root are kept' => ['/../file.xml', '/../file.xml'];
    }

    #[Test]
    #[DataProvider('normalizedPaths')]
    public function itNormalizesPathsLexically(string $path, string $expected): void
    {
        self::assertSame($expected, PathNormalizer::normalize($path));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function absolutePaths(): iterable
    {
        yield 'unix path' => ['/var/tmp', true];
        yield 'drive with slash' => ['C:/Users', true];
        yield 'drive with backslash' => ['c:\\Users', true];
        yield 'unc path' => ['\\\\server\\share', true];
        yield 'relative path' => ['en/reference', false];
        yield 'dot relative path' => ['./en', false];
        yield 'drive relative path' => ['C:Users', false];
        yield 'empty path' => ['', false];
    }

    #[Test]
    #[DataProvider('absolutePaths')]
    public function itDetectsAbsolutePaths(string $path, bool $expected): void
    {
        self::assertSame($expected, PathNormalizer::isAbsolute($path));
    }
}
