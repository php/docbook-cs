<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Report;

use DocbookCS\RelativePath;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\ReportException;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FileReport::class),
    CoversClass(RelativePath::class),
    CoversClass(Report::class),
    CoversClass(ReportException::class),
    CoversClass(Violation::class),
    //
    UsesClass(SourceRange::class),
]
final class ReportTest extends TestCase
{
    private function createViolation(
        string $message = 'Some problem',
        Severity $severity = Severity::ERROR,
    ): Violation {
        return new Violation(
            'DocbookCS.Test',
            'file.xml',
            $message,
            [new SourceRange(1, 0, 0)],
            severity: $severity,
        );
    }

    #[Test]
    public function itStartsWithZeroFilesScanned(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getScannedFilesCount());
    }

    #[Test]
    public function itCountsFileReportsAsScannedFiles(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('a.xml'));
        $report->addFileReport(new FileReport('b.xml'));
        $report->addFileReport(new FileReport('c.xml'));

        self::assertSame(3, $report->getScannedFilesCount());
    }

    #[Test]
    public function itStartsWithNoFileReports(): void
    {
        $report = new Report();

        self::assertSame([], $report->fileReports);
    }

    #[Test]
    public function itRejectsAddingFoundViolationsTwice(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([]);

        $this->expectException(ReportException::class);

        $fileReport->addFoundViolations([]);
    }

    #[Test]
    public function itRejectsAddingFinalViolationsBeforeFoundViolations(): void
    {
        $fileReport = new FileReport('file.xml');

        $this->expectException(ReportException::class);

        $fileReport->addFinalViolations([]);
    }

    #[Test]
    public function itAddsFileReport(): void
    {
        $report = new Report();
        $fileReport = new FileReport('src/chapter.xml');

        $report->addFileReport($fileReport);

        self::assertCount(1, $report->fileReports);
        self::assertSame($fileReport, $report->fileReports['src/chapter.xml']);
    }

    #[Test]
    public function itKeepsTheFileReportPathWhileRenderingItRelativeToWorkingDirectory(): void
    {
        $filePath = (getcwd() ?: '') . '/src/chapter.xml';
        $fileReport = new FileReport($filePath);

        self::assertSame($filePath, $fileReport->filePath);
        self::assertSame('src/chapter.xml', RelativePath::fromWorkingDirectory($fileReport->filePath));
    }

    #[Test]
    public function itKeysFileReportsByFilePath(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('a.xml'));
        $report->addFileReport(new FileReport('b.xml'));

        $keys = array_keys($report->fileReports);

        self::assertSame(['a.xml', 'b.xml'], $keys);
    }

    #[Test]
    public function itOverwritesFileReportWithSamePath(): void
    {
        $report = new Report();
        $first = new FileReport('file.xml');
        $second = new FileReport('file.xml');

        $report->addFileReport($first);
        $report->addFileReport($second);

        self::assertCount(1, $report->fileReports);
        self::assertSame($second, $report->fileReports['file.xml']);
    }

    #[Test]
    public function itReturnsTotalViolationsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(severity: Severity::WARNING),
        ]);

        $file2 = new FileReport('b.xml');
        $file2->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalFinalViolationCount());
    }

    #[Test]
    public function itReturnsZeroTotalViolationsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalFinalViolationCount());
    }

    #[Test]
    public function itReturnsTotalErrorsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(severity: Severity::WARNING),
        ]);

        $file2 = new FileReport('b.xml');
        $file2->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(),
        ]);

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalErrorLevelViolationCount());
    }

    #[Test]
    public function itReturnsZeroTotalErrorsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalErrorLevelViolationCount());
    }

    #[Test]
    public function itReturnsTotalWarningsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addFoundViolations([
            $this->createViolation(severity: Severity::WARNING),
            $this->createViolation(severity: Severity::ERROR),
        ]);

        $file2 = new FileReport('b.xml');
        $file2->addFoundViolations([
            $this->createViolation(severity: Severity::WARNING),
            $this->createViolation(severity: Severity::WARNING),
        ]);

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalWarningLevelViolationCount());
    }

    #[Test]
    public function itReturnsZeroTotalWarningsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalWarningLevelViolationCount());
    }

    #[Test]
    public function itHasFinalViolationsWhenFinalViolationsExist(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertTrue($report->hasFinalViolations());
    }

    #[Test]
    public function itHasNoFinalViolationsWhenEmpty(): void
    {
        $report = new Report();

        self::assertFalse($report->hasFinalViolations());
    }

    #[Test]
    public function itHasNoFinalViolationsWhenFilesAreClean(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        self::assertFalse($report->hasFinalViolations());
    }

    #[Test]
    public function itReturnsAllViolationsFromAllFiles(): void
    {
        $v1 = $this->createViolation(message: 'First');
        $v2 = $this->createViolation(message: 'Second');
        $v3 = $this->createViolation(message: 'Third');

        $file1 = new FileReport('a.xml');
        $file1->addFoundViolations([$v1, $v2]);

        $file2 = new FileReport('b.xml');
        $file2->addFoundViolations([$v3]);

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        $all = $report->getAllViolations();

        self::assertCount(3, $all);
        self::assertSame($v1, $all[0]);
        self::assertSame($v2, $all[1]);
        self::assertSame($v3, $all[2]);
    }

    #[Test]
    public function itReturnsEmptyListWhenNoViolations(): void
    {
        $report = new Report();

        self::assertSame([], $report->getAllViolations());
    }

    #[Test]
    public function itDoesNotCountWarningsAsErrors(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([
            $this->createViolation(severity: Severity::WARNING),
            $this->createViolation(severity: Severity::WARNING),
        ]);

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertSame(0, $report->getTotalErrorLevelViolationCount());
        self::assertSame(2, $report->getTotalWarningLevelViolationCount());
        self::assertSame(2, $report->getTotalFinalViolationCount());
    }

    #[Test]
    public function itDoesNotCountErrorsAsWarnings(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(),
        ]);

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertSame(2, $report->getTotalErrorLevelViolationCount());
        self::assertSame(0, $report->getTotalWarningLevelViolationCount());
        self::assertSame(2, $report->getTotalFinalViolationCount());
    }

    #[Test]
    public function itCountsCleanFileReportsAsScannedFiles(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean1.xml'));
        $report->addFileReport(new FileReport('clean2.xml'));
        $report->addFileReport(new FileReport('clean3.xml'));

        self::assertSame(3, $report->getScannedFilesCount());
        self::assertCount(3, $report->fileReports);
    }

    #[Test]
    public function itAggregatesFixingOutcome(): void
    {
        $first = new FileReport('first.xml');
        $first->markChanged();
        $first->recordFixingPass();
        $first->recordFixingPass();
        $first->addFoundViolations(array_fill(0, 4, $this->createViolation()));
        $first->addFinalViolations([$this->createViolation()]);

        $second = new FileReport('second.xml');
        $second->markChanged();
        $second->recordFixingPass();
        $second->addFoundViolations(array_fill(0, 3, $this->createViolation()));
        $second->addFinalViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($first);
        $report->addFileReport($second);

        self::assertSame(2, $report->getChangedFilesCount());
        self::assertSame(7, $report->getFoundViolationsCount());
        self::assertSame(5, $report->getAppliedFixesCount());
        self::assertSame(2, $report->getSkippedFixesCount());
        self::assertSame(5, $report->getFixedErrorCount());
        self::assertSame(0, $report->getFixedWarningCount());
        self::assertSame(3, $report->getFixingPassesCount());
    }

    #[Test]
    public function itAggregatesSniffTimes(): void
    {
        $first = new FileReport('first.xml', collectPerformance: true);
        $first->measureSniffer('Test.Sniff', static fn() => null);

        $second = new FileReport('second.xml', collectPerformance: true);
        $second->measureSniffer('Test.Sniff', static fn() => null);

        $report = new Report();
        $report->addFileReport($first);
        $report->addFileReport($second);

        self::assertSame(
            $first->sniffingTimes['Test.Sniff'] + $second->sniffingTimes['Test.Sniff'],
            $report->getSniffingTimes()['Test.Sniff'],
        );
    }

    #[Test]
    public function itAggregatesFixerTimes(): void
    {
        $first = new FileReport('first.xml', collectPerformance: true);
        $first->measureFixer('Test.Sniff', static fn() => null);

        $second = new FileReport('second.xml', collectPerformance: true);
        $second->measureFixer('Test.Sniff', static fn() => null);

        $report = new Report();
        $report->addFileReport($first);
        $report->addFileReport($second);

        self::assertSame(
            $first->fixingTimes['Test.Sniff'] + $second->fixingTimes['Test.Sniff'],
            $report->getFixingTimes()['Test.Sniff'],
        );
    }

    #[Test]
    public function itMeasuresSniffingAndReturnsTheOperationResult(): void
    {
        $fileReport = new FileReport('file.xml', collectPerformance: true);

        $result = $fileReport->measureSniffing(static function (): string {
            usleep(1_000);

            return 'result';
        });

        self::assertSame('result', $result);
        self::assertGreaterThan(0.0, $fileReport->totalSniffingTime);
    }

    #[Test]
    public function itMeasuresFixingAndReturnsTheOperationResult(): void
    {
        $report = new Report();


        $fileReport = new FileReport('file.xml', collectPerformance: true);
        $report->addFileReport($fileReport);

        $result = $fileReport->measureFixing(static function (): string {
            usleep(1_000);

            return 'result';
        });

        self::assertSame('result', $result);
        self::assertGreaterThan(0.0, $report->getTotalFixingTime());
    }

    #[Test]
    public function itRunsOperationsWithoutCollectingPerformanceByDefault(): void
    {
        $fileReport = new FileReport('file.xml');

        $result = $fileReport->measureSniffer('Test.Sniff', static fn(): string => 'result');

        self::assertSame('result', $result);
        self::assertSame([], $fileReport->sniffingTimes);
    }

    #[Test]
    public function itRecordsFixingPasses(): void
    {
        $fileReport = new FileReport('file.xml');

        $fileReport->recordFixingPass();
        $fileReport->recordFixingPass();

        self::assertSame(2, $fileReport->fixingPasses);
    }
}
