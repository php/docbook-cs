<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Report\Reporter;

use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\JsonReporter;
use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(FileReport::class),
    CoversClass(JsonReporter::class),
    CoversClass(Report::class),
    CoversClass(Violation::class),
]
final class JsonReporterTest extends TestCase
{
    private JsonReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new JsonReporter();
    }

    private function createViolation(
        string $message = 'Some problem',
        int $line = 1,
        string $sniffCode = 'DocbookCS.Test',
        Severity $severity = Severity::ERROR,
    ): Violation {
        return new Violation($sniffCode, 'filepath.xml', $line, $message, $severity);
    }

    #[Test]
    public function itReturnsValidJson(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertJson($output);
    }

    #[Test]
    public function itEndsWithNewline(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertStringEndsWith("\n", $output);
    }

    #[Test]
    public function itContainsTotalsForEmptyReport(): void
    {
        $report = new Report();

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(0, $data['totals']['files_scanned']);
        self::assertSame(0, $data['totals']['violations']);
        self::assertSame(0, $data['totals']['errors']);
        self::assertSame(0, $data['totals']['warnings']);
    }

    #[Test]
    public function itContainsEmptyFilesArrayForEmptyReport(): void
    {
        $report = new Report();

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame([], $data['files']);
    }

    #[Test]
    public function itCountsScannedFiles(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('a.xml'));
        $report->incrementFilesScanned();

        $report->addFileReport(new FileReport('b.xml'));
        $report->incrementFilesScanned();

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(2, $data['totals']['files_scanned'] ?? null);
    }

    #[Test]
    public function itSkipsFilesWithNoViolations(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame([], $data['files']);
    }

    #[Test]
    public function itIncludesFileWithViolations(): void
    {
        $fileReport = new FileReport('dirty.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertArrayHasKey('dirty.xml', $data['files']);
    }

    #[Test]
    public function itSetsViolationCountPerFile(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(message: 'First'));
        $fileReport->addViolation($this->createViolation(message: 'Second'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(2, $data['files']['file.xml']['violations']);
    }

    #[Test]
    public function itSetsLineInMessage(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(line: 42));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(42, $data['files']['file.xml']['messages'][0]['line']);
    }

    #[Test]
    public function itSetsSeverityInMessage(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame('warning', $data['files']['file.xml']['messages'][0]['severity']);
    }

    #[Test]
    public function itSetsMessageInMessage(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(message: 'Use <simpara> instead'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame('Use <simpara> instead', $data['files']['file.xml']['messages'][0]['message']);
    }

    #[Test]
    public function itSetsSourceInMessage(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(sniffCode: 'DocbookCS.ExceptionName'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame('DocbookCS.ExceptionName', $data['files']['file.xml']['messages'][0]['source']);
    }

    #[Test]
    public function itOutputsMultipleViolationsForOneFile(): void
    {
        $fileReport = new FileReport('multi.xml');
        $fileReport->addViolation($this->createViolation(message: 'First', line: 5));
        $fileReport->addViolation($this->createViolation(message: 'Second', line: 10));
        $fileReport->addViolation($this->createViolation(message: 'Third', line: 20));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertCount(3, $data['files']['multi.xml']['messages']);
    }

    #[Test]
    public function itOutputsMultipleFilesWithViolations(): void
    {
        $file1 = new FileReport('first.xml');
        $file1->addViolation($this->createViolation(message: 'Issue A'));

        $file2 = new FileReport('second.xml');
        $file2->addViolation($this->createViolation(message: 'Issue B'));

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertCount(2, $data['files']);
        self::assertArrayHasKey('first.xml', $data['files']);
        self::assertArrayHasKey('second.xml', $data['files']);
    }

    #[Test]
    public function itSkipsCleanFilesAmongDirtyOnes(): void
    {
        $cleanFile = new FileReport('clean.xml');

        $dirtyFile = new FileReport('dirty.xml');
        $dirtyFile->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($cleanFile);
        $report->addFileReport($dirtyFile);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertCount(1, $data['files']);
        self::assertArrayHasKey('dirty.xml', $data['files']);
        self::assertArrayNotHasKey('clean.xml', $data['files']);
    }

    #[Test]
    public function itCountsTotalViolations(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addViolation($this->createViolation());
        $file1->addViolation($this->createViolation());

        $file2 = new FileReport('b.xml');
        $file2->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(3, $data['totals']['violations']);
    }

    #[Test]
    public function itCountsTotalErrors(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(2, $data['totals']['errors']);
    }

    #[Test]
    public function itCountsTotalWarnings(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($fileReport);

        $data = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(2, $data['totals']['warnings']);
    }

    #[Test]
    public function itDoesNotEscapeSlashesInOutput(): void
    {
        $fileReport = new FileReport('path/to/file.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('path/to/file.xml', $output);
        self::assertStringNotContainsString('path\/to\/file.xml', $output);
    }

    #[Test]
    public function itUsesPrettyPrintedJson(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertStringContainsString("\n", rtrim($output));
    }

    /**
     * @return array{
     *     totals: array{
     *         files_scanned: int,
     *         violations: int,
     *         errors: int,
     *         warnings: int
     *     },
     *     files: array<string, array{
     *         violations: int,
     *         messages: list<array{
     *             line: int,
     *             severity: int,
     *             message: string,
     *             source: string
     *         }>
     *     }>
     * }
     */
    private function parseOutput(string $json): array
    {
        $data = json_decode($json, true);
        self::assertIsArray($data);

        // @phpstan-ignore-next-line
        return $data;
    }
}
