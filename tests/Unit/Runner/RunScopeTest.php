<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\DiffBaseResolver;
use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\FileChange;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixResult;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Git\GitClient;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Runner\RunPlanner;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Runner\SourceScope;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlProcessingResult;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(DiffPathLoader::class),
    CoversClass(EntityResolver::class),
    CoversClass(PathLoader::class),
    CoversClass(PathMatcher::class),
    CoversClass(RunCoordinator::class),
    CoversClass(RunPlanner::class),
    CoversClass(RunScopeResolver::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(ConfigData::class),
    UsesClass(DiffBaseResolver::class),
    UsesClass(DiffChangeset::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(GitClient::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(Line::class),
    UsesClass(NullProgress::class),
    UsesClass(Report::class),
    UsesClass(RunMode::class),
    UsesClass(RunPlan::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SimparaSniff::class),
    UsesClass(SniffEntry::class),
    UsesClass(SourceRange::class),
    UsesClass(SourceScope::class),
    UsesClass(UpstreamResolver::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlFileProcessor::class),
    UsesClass(XmlProcessingResult::class),
]
final class RunScopeTest extends TestCase
{
    private string $directory;
    private string $sourceFile;
    private string $targetFile;
    private string $entityFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/docbook-cs-run-scope-' . bin2hex(random_bytes(6));
        mkdir($this->directory);

        $this->sourceFile = $this->directory . '/source.xml';
        $this->targetFile = $this->directory . '/target.xml';
        $this->entityFile = $this->directory . '/entities.ent';

        file_put_contents($this->sourceFile, '<root>&target;</root>');
        file_put_contents($this->targetFile, '<target/>');
        file_put_contents($this->entityFile, '<!ENTITY target SYSTEM "target.xml">');
    }

    protected function tearDown(): void
    {
        @unlink($this->sourceFile);
        @unlink($this->targetFile);
        @unlink($this->entityFile);
        @rmdir($this->directory);
    }

    #[Test]
    public function itExpandsReferencedTargetsOnlyWhenWideScopeIsRequested(): void
    {
        $config = $this->config();

        self::assertSame(1, $this->executePaths($config, [$this->sourceFile])->filesScanned);
        self::assertSame(
            2,
            $this->executePaths(
                $config,
                [$this->sourceFile],
                wide: true,
            )->filesScanned,
        );
    }

    #[Test]
    public function itFixesExpandedXmlInItsTargetFileOnly(): void
    {
        file_put_contents($this->targetFile, '<para>Text</para>');
        $config = $this->config([
            new SniffEntry(SimparaSniff::class),
        ]);

        $report = $this->executePaths(
            $config,
            [$this->sourceFile],
            mode: RunMode::Fix,
            wide: true,
        );

        self::assertSame('<root>&target;</root>', file_get_contents($this->sourceFile));
        self::assertSame('<simpara>Text</simpara>', file_get_contents($this->targetFile));
        self::assertFalse($report->hasViolations());
    }

    #[Test]
    public function aDiffProvidesItsOwnFilesWithoutConfiguredIncludePaths(): void
    {
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [],
            excludePatterns: [],
            entityPaths: [],
            basePath: $this->directory,
        );

        $report = $this->executeDiff(
            $config,
            new DiffChangeset([
                new FileChange($this->sourceFile, [1]),
            ]),
        );

        self::assertSame(1, $report->filesScanned);
    }

    #[Test]
    public function aDiffPathUsingAProjectDirectoryKeepsItsSourceRanges(): void
    {
        $config = new ConfigData(
            projectRoots: [$this->directory => 'docs'],
            sniffs: [],
            includePaths: [],
            excludePatterns: [],
            entityPaths: [],
            basePath: $this->directory,
        );

        $report = $this->executeDiff(
            $config,
            new DiffChangeset([
                new FileChange('docs/source.xml', [1]),
            ]),
        );

        self::assertSame(1, $report->filesScanned);
    }

    /** @param list<SniffEntry> $sniffs */
    private function config(array $sniffs = []): ConfigData
    {
        return new ConfigData(
            projectRoots: [],
            sniffs: $sniffs,
            includePaths: [$this->sourceFile],
            excludePatterns: [],
            entityPaths: [$this->entityFile],
            basePath: $this->directory,
        );
    }

    /** @param list<string> $paths */
    private function executePaths(
        ConfigData $config,
        array $paths,
        RunMode $mode = RunMode::Sniff,
        bool $wide = false,
    ): Report {
        return new RunCoordinator()->run(
            new RunPlanner($config, $mode, $wide)->planPaths($paths),
        );
    }

    private function executeDiff(
        ConfigData $config,
        DiffChangeset $diff,
        RunMode $mode = RunMode::Sniff,
        bool $wide = false,
    ): Report {
        return new RunCoordinator()->run(
            new RunPlanner($config, $mode, $wide)->planDiff($diff),
        );
    }
}
