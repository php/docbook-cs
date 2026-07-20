<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Config\SniffEntry;
use DocbookCS\Diff\Diff;
use DocbookCS\Diff\DiffBaseResolver;
use DocbookCS\Diff\FileChange;
use DocbookCS\Diff\GitDiffProvider;
use DocbookCS\Diff\UpstreamResolver;
use DocbookCS\Git\GitClient;
use DocbookCS\Path\DiffPathLoader;
use DocbookCS\Path\EntityResolver;
use DocbookCS\Path\PathLoader;
use DocbookCS\Path\PathMatcher;
use DocbookCS\Progress\NullProgress;
use DocbookCS\Progress\ProgressInterface;
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
use DocbookCS\Sniff\SniffInterface;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ConfigData::class),
    CoversClass(EntityPreprocessor::class),
    CoversClass(EntityResolver::class),
    CoversClass(FileReport::class),
    CoversClass(NullProgress::class),
    CoversClass(PathLoader::class),
    CoversClass(PathMatcher::class),
    CoversClass(Report::class),
    CoversClass(RunCoordinator::class),
    CoversClass(RunMode::class),
    CoversClass(RunPlan::class),
    CoversClass(RunPlanner::class),
    CoversClass(SniffEntry::class),
    CoversClass(Violation::class),
    CoversClass(XmlFileProcessor::class),
    UsesClass(Diff::class),
    UsesClass(DiffBaseResolver::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(GitClient::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(Line::class),
    UsesClass(RunScopeResolver::class),
    UsesClass(SourceRange::class),
    UsesClass(SourceScope::class),
    UsesClass(UpstreamResolver::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlProcessingResult::class),
]
final class SniffRunnerTest extends TestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../../fixtures/sniff_runner/default';

    /** @param list<SniffEntry> $sniffs */
    private function createConfig(array $sniffs = []): ConfigData
    {
        return new ConfigData(
            [],
            $sniffs,
            [self::FIXTURE_DIR],
            [],
            [],
            self::FIXTURE_DIR,
        );
    }

    #[Test] // TODO: should be integration
    public function itProcessesFilesWithoutViolations(): void
    {
        $config = $this->createConfig();

        $runner = new RunCoordinator();
        $report = $runner->run($this->planPaths($config));

        self::assertSame(2, $report->getFilesScanned());
        self::assertFalse($report->hasViolations());
        self::assertCount(0, $report->getFileReports());
    }

    #[Test] // TODO: should be integration
    public function itUsesOverridePathsWhenProvided(): void
    {
        $config = $this->createConfig();

        $runner = new RunCoordinator();
        $report = $runner->run($this->planPaths(
            $config,
            [self::FIXTURE_DIR . '/../override'],
        ));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test] // TODO: should be integration
    public function itCallsProgressMethods(): void
    {
        $progress = $this->createMock(ProgressInterface::class);

        $progress->expects($this->once())
            ->method('start')
            ->with(2);

        $progress->expects($this->exactly(2))
            ->method('advance');

        $progress->expects($this->once())
            ->method('finish');

        $config = $this->createConfig();

        $runner = new RunCoordinator($progress);
        $runner->run($this->planPaths($config));
    }

    #[Test] // TODO: should be integration
    public function itAddsFileReportsForFilesWithViolations(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $file->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation message',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);

        $runner = new RunCoordinator();
        $report = $runner->run($this->planPaths($config));

        self::assertSame(2, $report->getFilesScanned());
        self::assertCount(2, $report->getFileReports());
        self::assertTrue($report->hasViolations());
    }

    #[Test] // TODO: should be integration
    public function itStoresAbsolutePathsInFileReports(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $file->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);

        $runner = new RunCoordinator();
        $report = $runner->run($this->planPaths($config));

        foreach ($report->getFileReports() as $fileReport) {
            self::assertTrue(
                str_starts_with($fileReport->filePath, '/'),
                'Expected absolute path, got: ' . $fileReport->filePath,
            );
        }
    }

    #[Test] // TODO: should be integration
    public function itPassesPropertiesToSniffs(): void
    {
        $sniffClass = new class (RunMode::Sniff) implements SniffInterface {
            public static string $captured = '';
            public static RunMode $capturedMode = RunMode::Sniff;

            public function __construct(public RunMode $mode)
            {
                self::$capturedMode = $mode;
            }

            public function setProperty(string $name, string $value): void
            {
                self::$captured = $value;
            }

            public static function getCode(): string
            {
                return 'Test.ConfigurableSniff';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [];
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniffClass::class, ['someProp' => 'someValue'])]);

        $runner = new RunCoordinator();
        $runner->run($this->planPaths($config, mode: RunMode::Fix));

        self::assertSame('someValue', $sniffClass::$captured);
        self::assertSame(RunMode::Fix, $sniffClass::$capturedMode);
    }

    #[Test] // TODO: should be integration
    public function itThrowsWhenSniffClassDoesNotExist(): void
    {
        $config = $this->createConfig(sniffs: [new SniffEntry('NonExistent\\FakeSniff')]);

        $runner = new RunCoordinator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        $runner->run($this->planPaths($config));
    }

    #[Test] // TODO: should be integration
    public function itThrowsWhenClassDoesNotImplementSniffInterface(): void
    {
        $config = $this->createConfig(sniffs: [new SniffEntry(\stdClass::class)]);

        $runner = new RunCoordinator();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('does not implement');

        $runner->run($this->planPaths($config));
    }

    #[Test] // TODO: should be integration
    public function itFiltersFilesToOnlyThoseInTheDiff(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange(self::FIXTURE_DIR . '/file_a.xml', [1])]);
        $report = $runner->run($this->planDiff($config, $diff));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test] // TODO: should be integration
    public function itScansNoFilesWhenDiffContainsNoMatchingPaths(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange('completely/different/file.xml', [1, 2, 3])]);
        $report = $runner->run($this->planDiff($config, $diff));

        self::assertSame(0, $report->getFilesScanned());
    }

    #[Test] // TODO: should be integration
    public function itMatchesWhenDiffPathEqualsDiscoveredPath(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $discoveredPath = self::FIXTURE_DIR . '/file_a.xml';

        $diff = new Diff([new FileChange($discoveredPath, [1])]);
        $report = $runner->run($this->planDiff($config, $diff));

        self::assertSame(1, $report->getFilesScanned());
    }

    #[Test] // TODO: should be integration
    public function itScansAllFilesWhenNoDiffIsGiven(): void
    {
        $config = $this->createConfig();
        $runner = new RunCoordinator();

        $report = $runner->run($this->planPaths($config));

        self::assertSame(2, $report->getFilesScanned());
    }

    #[Test] // TODO: should be integration
    public function itScansLexicallyEquivalentWideTargetsOnlyOnce(): void
    {
        $directory = sys_get_temp_dir() . '/docbook-cs-scan-' . bin2hex(random_bytes(6));
        mkdir($directory);

        $sourceFile = $directory . '/source.xml';
        $targetFile = $directory . '/target.xml';
        $entityFile = $directory . '/bridge.ent';

        file_put_contents($sourceFile, '<root>&bridge;</root>');
        file_put_contents($targetFile, '<target/>');
        file_put_contents($entityFile, '&target;');

        try {
            $config = new ConfigData([], [], [], [], [], $directory);
            $resolver = new RunScopeResolver(
                $config,
                [
                    'bridge' => $entityFile,
                    'target' => $directory . '/./target.xml',
                ],
                wide: true,
            );
            $plan = new RunPlan(
                mode: RunMode::Sniff,
                sniffs: [],
                targets: $resolver->resolvePaths([$directory . '/.']),
                entities: [
                    'bridge' => '&target;',
                    'target' => '<target/>',
                ],
            );

            $report = new RunCoordinator()->run($plan);

            self::assertSame(2, $report->getFilesScanned());
        } finally {
            @unlink($sourceFile);
            @unlink($targetFile);
            @unlink($entityFile);
            @rmdir($directory);
        }
    }

    #[Test] // TODO: should be integration
    public function itReportsNoViolationsForFilesInDiffWithoutAddedLines(): void
    {
        $sniff = new class (RunMode::Sniff) implements SniffInterface {
            public function __construct(public RunMode $mode)
            {
            }

            public static function getCode(): string
            {
                return 'Test.ViolatingSniff';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                return [
                    new Violation(
                        sniffCode: 'Test.ViolatingSniff',
                        filePath: $file->path,
                        line: 1,
                        beginOffset: 0,
                        untilOffset: 0,
                        message: 'Test violation',
                        severity: Severity::WARNING,
                    ),
                ];
            }

            public function setProperty(string $name, string $value): void
            {
            }
        };

        $config = $this->createConfig(sniffs: [new SniffEntry($sniff::class)]);
        $runner = new RunCoordinator();

        $diff = new Diff([new FileChange(self::FIXTURE_DIR . '/file_a.xml', [])]);
        $report = $runner->run($this->planDiff($config, $diff));

        self::assertSame(1, $report->getFilesScanned());
        self::assertFalse($report->hasViolations());
    }

    /** @param list<string>|null $paths */
    private function planPaths(ConfigData $config, ?array $paths = null, RunMode $mode = RunMode::Sniff): RunPlan
    {
        return new RunPlanner($config, $mode)->planPaths($paths ?? $config->getIncludePaths());
    }

    private function planDiff(ConfigData $config, Diff $diff, RunMode $mode = RunMode::Sniff): RunPlan
    {
        return new RunPlanner($config, $mode)->planDiff($diff);
    }
}
