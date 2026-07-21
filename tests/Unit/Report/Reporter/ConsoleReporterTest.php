<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Report\Reporter;

use DocbookCS\RelativePath;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\ConsoleReporter;
use DocbookCS\Violation\Severity;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ConsoleReporter::class),
    CoversClass(FileReport::class),
    CoversClass(Report::class),
    CoversClass(Violation::class),
    //
    UsesClass(RelativePath::class),
    UsesClass(SourceRange::class),
]
final class ConsoleReporterTest extends TestCase
{
    private ConsoleReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new ConsoleReporter(useColors: false);
    }

    private function createViolation(
        string $message = 'Some problem',
        int $line = 1,
        string $sniffCode = 'DocbookCS.Test',
        Severity $severity = Severity::ERROR,
        string $filePath = 'filepath.xml',
    ): Violation {
        return new Violation($sniffCode, $filePath, $line, 0, 0, $message, severity: $severity);
    }

    #[Test]
    public function itReturnsNonEmptyStringForEmptyReport(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertNotEmpty($output);
    }

    #[Test]
    public function itShowsOkSummaryWhenNoViolations(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));
        $report->incrementFilesScanned();

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('OK -- 1 file(s) scanned, no violations found.', $output);
    }

    #[Test]
    public function itShowsViolationSummaryWhenViolationsExist(): void
    {
        $fileReport = new FileReport('dirty.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($fileReport);
        $report->incrementFilesScanned();

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FOUND 2 violation(s) (1 error(s), 1 warning(s)) in 1 file(s).', $output);
    }

    #[Test]
    public function itShowsFixingOutcome(): void
    {
        $report = new Report();
        $report->recordModifiedFile();
        $report->recordModifiedFile();
        $report->recordFixPass(applied: 3, skipped: 1);
        $report->recordFixPass(applied: 2, skipped: 1);
        $report->recordFixPass(applied: 2, skipped: 0);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(
            'Applied 7 fix(es) in 2 file(s) across 3 fixing pass(es). Skipped 2 fix(es).',
            $output,
        );
    }

    #[Test]
    public function itShowsFilePathInHeader(): void
    {
        $fileReport = new FileReport('src/broken.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FILE: src/broken.xml', $output);
    }

    #[Test]
    public function itRendersAbsoluteFilePathRelativeToWorkingDirectory(): void
    {
        $fileReport = new FileReport((getcwd() ?: '') . '/src/broken.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FILE: src/broken.xml', $output);
    }

    #[Test]
    public function itShowsDashSeparatorAfterFileHeader(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        $expectedDashes = str_repeat('-', 6 + strlen('file.xml'));
        self::assertStringContainsString($expectedDashes, $output);
    }

    #[Test]
    public function itCapsTheDashSeparatorAt80Characters(): void
    {
        $longPath = str_repeat('a', 200) . '.xml';
        $fileReport = new FileReport($longPath);
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(str_repeat('-', 80), $output);
        self::assertStringNotContainsString(str_repeat('-', 81), $output);
    }

    #[Test]
    public function itShowsLineNumberInViolation(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(line: 42));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('  42 |', $output);
    }

    #[Test]
    public function itRightAlignsLineNumberIn4CharWidth(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(line: 5));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('    5 |', $output);
    }

    #[Test]
    public function itShowsMessageInViolation(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(message: 'Use <simpara> instead'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('Use <simpara> instead', $output);
    }

    #[Test]
    public function itShowsSniffCodeInViolation(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(sniffCode: 'DocbookCS.ExceptionName'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('DocbookCS.ExceptionName', $output);
    }

    #[Test]
    public function itShowsErrorSeverityLabel(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('ERROR', $output);
    }

    #[Test]
    public function itShowsMultipleViolationsForOneFile(): void
    {
        $fileReport = new FileReport('multi.xml');
        $fileReport->addViolation($this->createViolation(message: 'First issue', line: 5));
        $fileReport->addViolation($this->createViolation(message: 'Second issue', line: 10));
        $fileReport->addViolation($this->createViolation(message: 'Third issue', line: 20));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('First issue', $output);
        self::assertStringContainsString('Second issue', $output);
        self::assertStringContainsString('Third issue', $output);
    }

    #[Test]
    public function itShowsMultipleFileHeaders(): void
    {
        $file1 = new FileReport('first.xml');
        $file1->addViolation($this->createViolation(message: 'Issue A'));

        $file2 = new FileReport('second.xml');
        $file2->addViolation($this->createViolation(message: 'Issue B'));

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FILE: first.xml', $output);
        self::assertStringContainsString('FILE: second.xml', $output);
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

        $output = $this->reporter->generate($report);

        self::assertStringNotContainsString('FILE: clean.xml', $output);
        self::assertStringContainsString('FILE: dirty.xml', $output);
    }

    #[Test]
    public function itShowsScannedFileCountInOkSummary(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('a.xml'));
        $report->incrementFilesScanned();
        $report->addFileReport(new FileReport('b.xml'));
        $report->incrementFilesScanned();
        $report->addFileReport(new FileReport('c.xml'));
        $report->incrementFilesScanned();

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('3 file(s) scanned', $output);
    }

    #[Test]
    public function itCountsFilesWithViolationsInFoundSummary(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addViolation($this->createViolation());

        $file2 = new FileReport('b.xml');
        $file2->addViolation($this->createViolation());

        $cleanFile = new FileReport('c.xml');

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);
        $report->addFileReport($cleanFile);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('in 3 file(s).', $output);
    }

    #[Test]
    public function itAppliesAnsiCodesWhenColorsEnabled(): void
    {
        $reporter = new ConsoleReporter(useColors: true);

        $report = new Report();
        $report->incrementFilesScanned();

        $output = $reporter->generate($report);

        self::assertStringContainsString("\033[", $output);
    }

    #[Test]
    public function itOmitsAnsiCodesWhenColorsDisabled(): void
    {
        $report = new Report();
        $report->incrementFilesScanned();

        $output = $this->reporter->generate($report);

        self::assertStringNotContainsString("\033[", $output);
    }

    #[Test]
    public function itUsesColorsEnabledByDefault(): void
    {
        $reporter = new ConsoleReporter();

        $report = new Report();
        $report->incrementFilesScanned();

        $output = $reporter->generate($report);

        self::assertStringContainsString("\033[", $output);
    }

    #[Test]
    public function itPadsSeverityToSevenCharacters(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::ERROR));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('ERROR  ', $output);
    }

    #[Test]
    public function itSeparatesFieldsWithPipes(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(
            message: 'Test message',
            line: 1,
            sniffCode: 'DocbookCS.Test',
            severity: Severity::ERROR,
        ));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        $lines = explode(PHP_EOL, $output);
        $violationLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, 'Test message')) {
                $violationLine = $line;
                break;
            }
        }

        self::assertNotEmpty($violationLine);
        self::assertSame(3, substr_count($violationLine, '|'));
    }

    #[Test]
    public function itShowsNoPerformanceDataWhenEmpty(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->incrementFilesScanned();

        $output = $reporter->generate($report);

        self::assertStringContainsString('No performance data available.', $output);
    }

    #[Test]
    public function itShowsPerformanceSectionWithHeader(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->incrementFilesScanned();

        $report->setTotalTime(2.0);
        $report->addSniffTime('SniffA', 1.0);

        $output = $reporter->generate($report);

        self::assertStringContainsString('PERFORMANCE', $output);
        self::assertStringContainsString('Total runtime: 2.000s', $output);
    }

    #[Test]
    public function itSortsSniffTimesBySlowestFirst(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->setTotalTime(3.0);

        $report->addSniffTime('FastSniff', 0.5);
        $report->addSniffTime('SlowSniff', 2.0);
        $report->addSniffTime('MediumSniff', 1.0);

        $output = $reporter->generate($report);

        $slowPos = strpos($output, 'SlowSniff');
        $mediumPos = strpos($output, 'MediumSniff');
        $fastPos = strpos($output, 'FastSniff');

        self::assertTrue($slowPos < $mediumPos);
        self::assertTrue($mediumPos < $fastPos);
    }

    #[Test]
    public function itDisplaysTimeAndPercentagePerSniff(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->setTotalTime(2.0);

        $report->addSniffTime('SniffA', 1.0); // 50%

        $output = $reporter->generate($report);

        self::assertStringContainsString('1.000s ( 50.0%)', $output);
    }

    #[Test]
    public function itDisplaysFixingTimeAndPercentage(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->setTotalTime(2.0);
        $report->addFixTime(0.5);

        $output = $reporter->generate($report);

        self::assertStringContainsString('Fixing', $output);
        self::assertStringContainsString('0.500s ( 25.0%)', $output);
    }

    #[Test]
    public function itDoesNotShowPerformanceWhenDisabled(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: false);

        $report = new Report();
        $report->setTotalTime(2.0);
        $report->addSniffTime('SniffA', 1.0);

        $output = $reporter->generate($report);

        self::assertStringNotContainsString('PERFORMANCE', $output);
    }
}
