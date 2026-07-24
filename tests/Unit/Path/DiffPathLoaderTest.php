<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Path;

use DocbookCS\Diff\Diff;
use DocbookCS\Diff\FileChange;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\PathMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(Diff::class),
    CoversClass(DiffPathLoader::class),
    CoversClass(PathMatcher::class),
    //
    UsesClass(FileChange::class),
]
final class DiffPathLoaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/docbook-cs-diff-path-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/chapter.xml');
        @unlink($this->directory . '/excluded.xml');
        @rmdir($this->directory);
    }

    #[Test]
    public function itLoadsChangedXmlFilesWithoutScanningConfiguredPaths(): void
    {
        $file = $this->directory . '/chapter.xml';
        file_put_contents($file, '<chapter/>');

        $loader = new DiffPathLoader(
            new Diff([new FileChange('docs/chapter.xml', [1])]),
            workingDirectory: dirname($this->directory),
            basePath: $this->directory,
            projectRoots: [$this->directory => 'docs'],
            matcher: new PathMatcher($this->directory, []),
        );

        $change = $loader->load()->changeFor($file);

        self::assertNotNull($change);
        self::assertSame([1], $change->addedLineNumbers);
    }

    #[Test]
    public function itIgnoresMissingNonXmlAndExcludedFiles(): void
    {
        $excluded = $this->directory . '/excluded.xml';
        file_put_contents($excluded, '<chapter/>');

        $loader = new DiffPathLoader(
            new Diff([
                new FileChange('excluded.xml', [1]),
                new FileChange('notes.txt', [1]),
                new FileChange('missing.xml', [1]),
            ]),
            workingDirectory: $this->directory,
            basePath: $this->directory,
            projectRoots: [],
            matcher: new PathMatcher($this->directory, ['excluded.xml']),
        );

        self::assertSame([], $loader->load()->fileChanges);
    }

    #[Test]
    public function itLoadsAbsolutePaths(): void
    {
        $file = $this->directory . '/chapter.xml';
        file_put_contents($file, '<chapter/>');

        $loader = new DiffPathLoader(
            new Diff([new FileChange($file, [1])]),
            workingDirectory: $this->directory,
            basePath: $this->directory,
            projectRoots: [],
            matcher: new PathMatcher($this->directory, []),
        );

        self::assertNotNull($loader->load()->changeFor($file));
    }

    #[Test]
    public function itNormalisesParentDirectorySegments(): void
    {
        $file = $this->directory . '/chapter.xml';
        file_put_contents($file, '<chapter/>');

        $loader = new DiffPathLoader(
            new Diff([new FileChange('nested/../chapter.xml', [1])]),
            workingDirectory: $this->directory,
            basePath: $this->directory,
            projectRoots: [],
            matcher: new PathMatcher($this->directory, []),
        );

        self::assertNotNull($loader->load()->changeFor($file));
    }
}
