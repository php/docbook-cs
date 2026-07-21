<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Path\PathMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(PathMatcher::class),
]
final class PathMatcherTest extends TestCase
{
    /**
     * @param list<string> $patterns fnmatch()-compatible patterns.
     */
    #[Test]
    #[DataProvider('exclusionProvider')]
    public function itMatchesExclusions(
        string $basePath,
        array $patterns,
        string $filePath,
        bool $expectedExcluded,
    ): void {
        $matcher = new PathMatcher($basePath, $patterns);

        self::assertIsList($patterns);
        self::assertSame($expectedExcluded, $matcher->isExcluded($filePath));
        self::assertSame(!$expectedExcluded, $matcher->isIncluded($filePath));
    }

    /** @return iterable<string, array{string, list<string>, string, bool}> */
    public static function exclusionProvider(): iterable
    {
        yield 'wildcard matches skeleton.xml in single subdirectory' => [
            '/project/doc',
            ['*/skeleton.xml'],
            '/project/doc/reference/skeleton.xml',
            true,
        ];

        yield 'specific subdirectory pattern' => [
            '/project',
            ['reference/*/versions.xml'],
            '/project/reference/strings/versions.xml',
            true,
        ];

        yield 'non-matching pattern leaves file included' => [
            '/project/doc',
            ['*/skeleton.xml'],
            '/project/doc/reference/strlen.xml',
            false,
        ];

        yield 'empty patterns exclude nothing' => [
            '',
            [],
            '/any/path.xml',
            false,
        ];

        yield 'multiple patterns - first matches' => [
            '/project',
            ['*/foo.xml', '*/bar.xml'],
            '/project/foo.xml',
            false,
        ];

        yield 'multiple patterns - basename fallback matches' => [
            '/project',
            ['foo.xml', 'bar.xml'],
            '/project/bar.xml',
            true,
        ];

        yield 'multiple patterns - first basename matches' => [
            '/project',
            ['foo.xml', 'bar.xml'],
            '/project/foo.xml',
            true,
        ];

        yield 'backslash paths are normalized' => [
            'C:\\project\\doc',
            ['*.xml'],
            'C:\\project\\doc\\skeleton.xml',
            true,
        ];

        yield 'basename-only pattern matches via fallback' => [
            '/project/doc',
            ['skeleton.xml'],
            '/project/doc/reference/skeleton.xml',
            true,
        ];

        yield 'deep wildcard matches across directories' => [
            '/project/doc',
            ['*/skeleton.xml'],
            '/project/doc/a/b/skeleton.xml',
            true,
        ];
    }
}
