<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\ConfigData;
use DocbookCS\Diff\DiffChangeset;
use DocbookCS\Diff\FileChange;
use DocbookCS\Progress\ProgressInterface;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunPlanner;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
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
}
