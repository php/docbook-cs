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
use DocbookCS\Fix\FixerException;
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
use DocbookCS\Runner\RunScope;
use DocbookCS\Runner\RunScopeResolver;
use DocbookCS\Runner\ViolationScopeFilter;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Runner\XmlFixRunner;
use DocbookCS\Runner\XmlSniffRunner;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use DocbookCS\Xml\XmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FixerException::class),
    CoversClass(RunCoordinator::class),
    //
    UsesClass(AbstractSniff::class),
    UsesClass(ConfigData::class),
    UsesClass(DiffBaseResolver::class),
    UsesClass(DiffChangeset::class),
    UsesClass(DiffPathLoader::class),
    UsesClass(EntityExpansionMarker::class),
    UsesClass(EntityPreprocessor::class),
    UsesClass(EntityResolver::class),
    UsesClass(File::class),
    UsesClass(FileChange::class),
    UsesClass(FileReport::class),
    UsesClass(Fix::class),
    UsesClass(FixApplier::class),
    UsesClass(FixPlan::class),
    UsesClass(FixResult::class),
    UsesClass(GitClient::class),
    UsesClass(GitDiffProvider::class),
    UsesClass(NullProgress::class),
    UsesClass(PathLoader::class),
    UsesClass(PathMatcher::class),
    UsesClass(Report::class),
    UsesClass(RunMode::class),
    UsesClass(RunPlan::class),
    UsesClass(RunPlanner::class),
    UsesClass(RunScope::class),
    UsesClass(RunScopeResolver::class),
    UsesClass(SimparaFixer::class),
    UsesClass(SimparaSniff::class),
    UsesClass(SniffEntry::class),
    UsesClass(SourceRange::class),
    UsesClass(UpstreamResolver::class),
    UsesClass(Violation::class),
    UsesClass(ViolationScopeFilter::class),
    UsesClass(XmlFileProcessor::class),
    UsesClass(XmlFixRunner::class),
    UsesClass(XmlParser::class),
    UsesClass(XmlSniffRunner::class),
]
final class RunCoordinatorFileFailureTest extends TestCase
{
    #[Test]
    public function itReportsFilesThatBecomeUnreadableBeforeProcessing(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        $xmlFilePath = $filePath . '.xml';
        rename($filePath, $xmlFilePath);
        file_put_contents($xmlFilePath, '<root/>');

        $progress = new class ($xmlFilePath) implements ProgressInterface {
            public function __construct(private string $filePath)
            {
            }

            public function start(int $totalFiles): void
            {
                @unlink($this->filePath);
            }

            public function advance(string $filePath, int $violations): void
            {
            }

            public function finish(): void
            {
            }
        };

        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [$xmlFilePath],
            excludePatterns: [],
            entityPaths: [],
            basePath: dirname($xmlFilePath),
        );

        $report = new RunCoordinator($progress)->runWithMetrics(
            new RunPlanner($config)->planPaths($config->getIncludePaths()),
        );

        self::assertTrue($report->hasFinalViolations());
        self::assertSame('DocbookCS.Internal', $report->getAllViolations()[0]->sniffCode);
        self::assertStringContainsString('Could not read file', $report->getAllViolations()[0]->message);
    }

    #[Test]
    public function itKeepsUnreadableFileErrorsInDiffRuns(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        $xmlFilePath = $filePath . '.xml';
        rename($filePath, $xmlFilePath);
        file_put_contents($xmlFilePath, '<root/>');

        $progress = $this->createMock(ProgressInterface::class);
        $progress->expects($this->once())->method('start')->willReturnCallback(
            static function () use ($xmlFilePath): void {
                @unlink($xmlFilePath);
            },
        );
        $progress->expects($this->once())->method('advance');
        $progress->expects($this->once())->method('finish');
        $config = new ConfigData(
            projectRoots: [],
            sniffs: [],
            includePaths: [$xmlFilePath],
            excludePatterns: [],
            entityPaths: [],
            basePath: dirname($xmlFilePath),
        );
        $diff = new DiffChangeset([new FileChange($xmlFilePath, [42])]);

        $report = new RunCoordinator($progress)->runWithMetrics(
            new RunPlanner($config)->planDiff($diff),
        );

        self::assertTrue($report->hasFinalViolations());
        self::assertSame('DocbookCS.Internal', $report->getAllViolations()[0]->sniffCode);
    }

    #[Test]
    public function itFailsClearlyWhenFixedContentCannotBePersisted(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Unix file permissions are required.');
        }

        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-');
        self::assertIsString($filePath);
        file_put_contents($filePath, '<root><para>Text</para></root>');
        chmod($filePath, 0444);

        try {
            $this->expectException(FixerException::class);
            $this->expectExceptionMessageIs('Could not write fixed file: ' . $filePath);

            new RunCoordinator()->runWithMetrics(new RunPlan(
                mode: RunMode::Fix,
                sniffs: [new SniffEntry(SimparaSniff::class)],
                targets: [$filePath => null],
                entities: [],
            ));
        } finally {
            chmod($filePath, 0644);
            @unlink($filePath);
        }
    }
}
