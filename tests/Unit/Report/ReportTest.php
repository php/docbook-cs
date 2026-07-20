<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Report;

use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FileReport::class),
    CoversClass(Report::class),
    CoversClass(Violation::class),
]
final class ReportTest extends TestCase
{
    private function createViolation(
        string $message = 'Some problem',
        int $line = 1,
        string $sniffCode = 'DocbookCS.Test',
        Severity $severity = Severity::ERROR,
        string $filePath = 'file.xml',
    ): Violation {
        return new Violation($sniffCode, $filePath, $line, $message, $severity);
    }

    #[Test]
    public function itStartsWithZeroFilesScanned(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getFilesScanned());
    }

    #[Test]
    public function itIncrementsFilesScanned(): void
    {
        $report = new Report();
        $report->incrementFilesScanned();
        $report->incrementFilesScanned();
        $report->incrementFilesScanned();

        self::assertSame(3, $report->getFilesScanned());
    }

    #[Test]
    public function itStartsWithNoFileReports(): void
    {
        $report = new Report();

        self::assertSame([], $report->getFileReports());
    }

    #[Test]
    public function itAddsFileReport(): void
    {
        $report = new Report();
        $fileReport = new FileReport('src/chapter.xml');

        $report->addFileReport($fileReport);

        self::assertCount(1, $report->getFileReports());
        self::assertSame($fileReport, $report->getFileReports()['src/chapter.xml']);
    }

    #[Test]
    public function itKeysFileReportsByFilePath(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('a.xml'));
        $report->addFileReport(new FileReport('b.xml'));

        $keys = array_keys($report->getFileReports());

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

        self::assertCount(1, $report->getFileReports());
        self::assertSame($second, $report->getFileReports()['file.xml']);
    }

    #[Test]
    public function itReturnsTotalViolationsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addViolation($this->createViolation(severity: Severity::ERROR));
        $file1->addViolation($this->createViolation(severity: Severity::WARNING));

        $file2 = new FileReport('b.xml');
        $file2->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalViolations());
    }

    #[Test]
    public function itReturnsZeroTotalViolationsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalViolations());
    }

    #[Test]
    public function itReturnsTotalErrorsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addViolation($this->createViolation(severity: Severity::ERROR));
        $file1->addViolation($this->createViolation(severity: Severity::WARNING));

        $file2 = new FileReport('b.xml');
        $file2->addViolation($this->createViolation(severity: Severity::ERROR));
        $file2->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalErrors());
    }

    #[Test]
    public function itReturnsZeroTotalErrorsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalErrors());
    }

    #[Test]
    public function itReturnsTotalWarningsAcrossAllFiles(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addViolation($this->createViolation(severity: Severity::WARNING));
        $file1->addViolation($this->createViolation(severity: Severity::ERROR));

        $file2 = new FileReport('b.xml');
        $file2->addViolation($this->createViolation(severity: Severity::WARNING));
        $file2->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        self::assertSame(3, $report->getTotalWarnings());
    }

    #[Test]
    public function itReturnsZeroTotalWarningsWhenEmpty(): void
    {
        $report = new Report();

        self::assertSame(0, $report->getTotalWarnings());
    }

    #[Test]
    public function itHasViolationsWhenViolationsExist(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertTrue($report->hasViolations());
    }

    #[Test]
    public function itHasNoViolationsWhenEmpty(): void
    {
        $report = new Report();

        self::assertFalse($report->hasViolations());
    }

    #[Test]
    public function itHasNoViolationsWhenFilesAreClean(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        self::assertFalse($report->hasViolations());
    }

    #[Test]
    public function itReturnsAllViolationsFromAllFiles(): void
    {
        $v1 = $this->createViolation(message: 'First');
        $v2 = $this->createViolation(message: 'Second');
        $v3 = $this->createViolation(message: 'Third');

        $file1 = new FileReport('a.xml');
        $file1->addViolation($v1);
        $file1->addViolation($v2);

        $file2 = new FileReport('b.xml');
        $file2->addViolation($v3);

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
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertSame(0, $report->getTotalErrors());
        self::assertSame(2, $report->getTotalWarnings());
        self::assertSame(2, $report->getTotalViolations());
    }

    #[Test]
    public function itDoesNotCountErrorsAsWarnings(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($fileReport);

        self::assertSame(2, $report->getTotalErrors());
        self::assertSame(0, $report->getTotalWarnings());
        self::assertSame(2, $report->getTotalViolations());
    }

    #[Test]
    public function filesScannedIsIndependentOfFileReports(): void
    {
        $report = new Report();
        $report->incrementFilesScanned();
        $report->incrementFilesScanned();
        $report->incrementFilesScanned();

        self::assertSame(3, $report->getFilesScanned());
        self::assertCount(0, $report->getFileReports());
    }
}
