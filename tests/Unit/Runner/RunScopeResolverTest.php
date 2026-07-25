<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\FileChange;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Runner\RunScopeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(PathMatcher::class),
    CoversClass(RunScopeResolver::class),
    //
    UsesClass(ConfigData::class),
    UsesClass(DiffChangeset::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(FileChange::class),
    UsesClass(PathLoader::class),
]
final class RunScopeResolverTest extends TestCase
{
    private string $directory;
    private string $sourceFile;
    private string $targetFile;
    private string $entityFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/docbook-cs-scope-' . bin2hex(random_bytes(6));
        mkdir($this->directory);

        $this->sourceFile = $this->directory . '/source.xml';
        $this->targetFile = $this->directory . '/target.xml';
        $this->entityFile = $this->directory . '/bridge.ent';

        file_put_contents($this->sourceFile, '<root>&bridge;</root>');
        file_put_contents($this->targetFile, '<target/>');
        file_put_contents($this->entityFile, '&target;');
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceFile);
        @unlink($this->targetFile);
        @unlink($this->entityFile);
        @rmdir($this->directory);
    }

    #[Test]
    public function narrowScopeKeepsOnlySelectedFilesAndDiffLines(): void
    {
        $resolver = $this->resolver();

        $targets = $resolver->resolveDiff(
            new DiffChangeset([new FileChange('source.xml', [2, 3])]),
        );

        self::assertSame([2, 3], $targets[$this->sourceFile]?->addedLineNumbers);
        self::assertCount(1, $targets);
    }

    #[Test]
    public function wideScopeWidensSelectedFilesAndFollowsReferencedTargets(): void
    {
        $resolver = $this->resolver(wide: true);

        $targets = $resolver->resolveDiff(
            new DiffChangeset([new FileChange('source.xml', [2, 3])]),
        );

        self::assertNull($targets[$this->sourceFile]);
        self::assertNull($targets[$this->targetFile]);
        self::assertCount(2, $targets);
    }

    #[Test]
    public function expandedScopeHonorsTargetExclusions(): void
    {
        $resolver = new RunScopeResolver(
            $this->config(['target.xml']),
            [
                'bridge' => $this->entityFile,
                'target' => $this->targetFile,
            ],
            wide: true,
        );

        $targets = $resolver->resolvePaths([$this->sourceFile]);

        self::assertSame([$this->sourceFile => null], $targets);
    }

    #[Test]
    public function pathScopeResolvesRelativePathsAgainstTheWorkingDirectory(): void
    {
        $path = 'tests/fixtures/sniff_runner/default/file_a.xml';
        $absolutePath = (getcwd() ?: '.') . '/' . $path;

        $targets = $this->resolver()->resolvePaths([$path]);

        self::assertSame([$absolutePath => null], $targets);
    }

    #[Test]
    public function widePathScopeDoesNotDuplicateLexicallyEquivalentTargets(): void
    {
        $resolver = new RunScopeResolver(
            $this->config(),
            [
                'bridge' => $this->entityFile,
                'target' => $this->directory . '/./target.xml',
            ],
            wide: true,
        );

        self::assertSame(
            [
                $this->sourceFile => null,
                $this->targetFile => null,
            ],
            $resolver->resolvePaths([$this->directory . '/.']),
        );
    }

    #[Test]
    public function wideScopeIgnoresUnknownEntityReferences(): void
    {
        file_put_contents($this->sourceFile, '<root>&unknown;&bridge;</root>');

        $targets = $this->resolver(wide: true)->resolvePaths([$this->sourceFile]);

        self::assertSame(
            [
                $this->sourceFile => null,
                $this->targetFile => null,
            ],
            $targets,
        );
    }

    #[Test]
    public function wideScopeHandlesCyclicEntityReferences(): void
    {
        file_put_contents($this->entityFile, '&bridge;&target;');

        self::assertSame(
            [
                $this->sourceFile => null,
                $this->targetFile => null,
            ],
            $this->resolver(wide: true)->resolvePaths([$this->sourceFile]),
        );
    }

    #[Test]
    public function wideScopeNormalisesParentDirectorySegmentsInEntityPaths(): void
    {
        $resolver = new RunScopeResolver(
            $this->config(),
            [
                'bridge' => $this->directory . '/nested/../bridge.ent',
                'target' => $this->targetFile,
            ],
            wide: true,
        );

        self::assertSame(
            [
                $this->sourceFile => null,
                $this->targetFile => null,
            ],
            $resolver->resolvePaths([$this->sourceFile]),
        );
    }

    private function resolver(bool $wide = false): RunScopeResolver
    {
        return new RunScopeResolver(
            $this->config(),
            [
                'bridge' => $this->entityFile,
                'target' => $this->targetFile,
            ],
            $wide,
        );
    }

    /** @param list<string> $excludePatterns */
    private function config(array $excludePatterns = []): ConfigData
    {
        return new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [$this->sourceFile],
            excludePatterns: $excludePatterns,
            entityPaths: [],
            basePath: $this->directory,
        );
    }
}
