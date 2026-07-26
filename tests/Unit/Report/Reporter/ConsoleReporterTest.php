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
    ): Violation {
        return new Violation($sniffCode, 'filepath.xml', $message, [new SourceRange($line, 0, 0)], severity: $severity);
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

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('OK -- 1 file scanned, no violations found.', $output);
        self::assertStringNotContainsString('FIXING', $output);
    }

    #[Test]
    public function itShowsNoViolationsRemainingAfterFixing(): void
    {
        $report = new Report();

        $fileReport = new FileReport('fixed.xml');
        $violation = $this->createViolation();
        $fileReport->addFoundViolations([$violation]);
        $fileReport->addFinalViolations([]);
        $fileReport->markChanged();
        $fileReport->recordFixingPass();

        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(
            'FIXED 1 violation [1 error, 0 warnings] in 1 file, no violations remaining.',
            $output,
        );
        self::assertStringNotContainsString('REMAINING', $output);
        self::assertStringNotContainsString('OK --', $output);
        self::assertStringNotContainsString('passes)', $output);
    }

    #[Test]
    public function itShowsViolationsRemainingAfterFixing(): void
    {
        $fileReport = new FileReport('dirty.xml');
        $violation = $this->createViolation();
        $fileReport->addFoundViolations([$violation]);
        $fileReport->recordFixingPass();

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('REMAINING 1 violation [1 error, 0 warnings] in 1 file.', $output);
        self::assertStringNotContainsString('passes)', $output);
    }

    #[Test]
    public function itShowsViolationSummaryWhenViolationsExist(): void
    {
        $fileReport = new FileReport('dirty.xml');
        $fileReport->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(severity: Severity::WARNING),
        ]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FOUND 2 violations [1 error, 1 warning] in 1 file.', $output);
    }

    #[Test]
    public function itShowsRemainingViolationsAfterFixing(): void
    {
        $fileReport = new FileReport('dirty.xml');
        $violation = $this->createViolation();
        $fileReport->addFoundViolations([$violation]);
        $fileReport->recordFixingPass();

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(
            'REMAINING 1 violation [1 error, 0 warnings] in 1 file.',
            $output,
        );
    }

    #[Test]
    public function itShowsFixedViolationsAndAdditionalPasses(): void
    {
        $report = new Report();

        $first = new FileReport('first.xml');
        $first->markChanged();
        $first->recordFixingPass();
        $first->recordFixingPass();
        $first->addFoundViolations([
            ...array_fill(0, 3, $this->createViolation()),
            $this->createViolation(severity: Severity::WARNING),
        ]);
        $first->addFinalViolations([$this->createViolation(severity: Severity::WARNING)]);

        $second = new FileReport('second.xml');
        $second->markChanged();
        $second->recordFixingPass();
        $second->addFoundViolations([
            $this->createViolation(),
            $this->createViolation(severity: Severity::WARNING),
            $this->createViolation(severity: Severity::WARNING),
        ]);
        $second->addFinalViolations([$this->createViolation()]);

        $report->addFileReport($first);
        $report->addFileReport($second);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(
            'FIXED 5 violations [3 errors, 2 warnings] in 2 files (3 passes).',
            $output,
        );
        self::assertStringNotContainsString('FIXING', $output);
    }

    #[Test]
    public function itFormatsLargeSummaryCounts(): void
    {
        $fileReport = new FileReport('fixed.xml');
        $fileReport->markChanged();
        $fileReport->recordFixingPass();
        $fileReport->addFoundViolations(array_fill(0, 1_001, $this->createViolation()));
        $fileReport->addFinalViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString(
            'FIXED 1,000 violations [1,000 errors, 0 warnings] in 1 file.',
            $output,
        );
    }

    #[Test]
    public function itShowsFilePathInHeader(): void
    {
        $fileReport = new FileReport('src/broken.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FILE: src/broken.xml', $output);
    }

    #[Test]
    public function itRendersAbsoluteFilePathRelativeToWorkingDirectory(): void
    {
        $fileReport = new FileReport((getcwd() ?: '') . '/src/broken.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('FILE: src/broken.xml', $output);
    }

    #[Test]
    public function itShowsDashSeparatorAfterFileHeader(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

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
        $fileReport->addFoundViolations([$this->createViolation()]);

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
        $fileReport->addFoundViolations([$this->createViolation(line: 42)]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('  42 |', $output);
    }

    #[Test]
    public function itRightAlignsLineNumberIn4CharWidth(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation(line: 5)]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('    5 |', $output);
    }

    #[Test]
    public function itShowsMessageInViolation(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation(message: 'Use <simpara> instead')]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('Use <simpara> instead', $output);
    }

    #[Test]
    public function itShowsSniffCodeInViolation(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation(sniffCode: 'DocbookCS.ExceptionName')]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('DocbookCS.ExceptionName', $output);
    }

    #[Test]
    public function itShowsErrorSeverityLabel(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('ERROR', $output);
    }

    #[Test]
    public function itShowsMultipleViolationsForOneFile(): void
    {
        $fileReport = new FileReport('multi.xml');
        $fileReport->addFoundViolations([
            $this->createViolation(message: 'First issue', line: 5),
            $this->createViolation(message: 'Second issue', line: 10),
            $this->createViolation(message: 'Third issue', line: 20),
        ]);

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
        $file1->addFoundViolations([$this->createViolation(message: 'Issue A')]);

        $file2 = new FileReport('second.xml');
        $file2->addFoundViolations([$this->createViolation(message: 'Issue B')]);

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
        $dirtyFile->addFoundViolations([$this->createViolation()]);

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
        $report->addFileReport(new FileReport('b.xml'));
        $report->addFileReport(new FileReport('c.xml'));

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('3 files scanned', $output);
    }

    #[Test]
    public function itCountsFilesWithViolationsInFoundSummary(): void
    {
        $file1 = new FileReport('a.xml');
        $file1->addFoundViolations([$this->createViolation()]);

        $file2 = new FileReport('b.xml');
        $file2->addFoundViolations([$this->createViolation()]);

        $cleanFile = new FileReport('c.xml');

        $report = new Report();
        $report->addFileReport($file1);
        $report->addFileReport($file2);
        $report->addFileReport($cleanFile);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('in 2 files.', $output);
    }

    #[Test]
    public function itAppliesAnsiCodesWhenColorsEnabled(): void
    {
        $reporter = new ConsoleReporter(useColors: true);

        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        $output = $reporter->generate($report);

        self::assertStringContainsString("\033[", $output);
    }

    #[Test]
    public function itOmitsAnsiCodesWhenColorsDisabled(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        $output = $this->reporter->generate($report);

        self::assertStringNotContainsString("\033[", $output);
    }

    #[Test]
    public function itUsesColorsEnabledByDefault(): void
    {
        $reporter = new ConsoleReporter();

        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        $output = $reporter->generate($report);

        self::assertStringContainsString("\033[", $output);
    }

    #[Test]
    public function itPadsSeverityToSevenCharacters(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation()]);

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('ERROR  ', $output);
    }

    #[Test]
    public function itSeparatesFieldsWithPipes(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addFoundViolations([$this->createViolation(message: 'Test message')]);

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
        $report->addFileReport(new FileReport('clean.xml'));

        $output = $reporter->generate($report);

        self::assertStringContainsString('No performance data available.', $output);
    }

    #[Test]
    public function itShowsPerformanceSectionWithHeader(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->measureWallTime(function () use ($report): void {
            $fileReport = new FileReport('clean.xml', collectPerformance: true);
            $fileReport->measureSniffer('SniffA', static fn() => usleep(1_000));
            $report->addFileReport($fileReport);
        });

        $output = $reporter->generate($report);

        self::assertStringContainsString('PERFORMANCE', $output);
        self::assertSame(1, substr_count($output, 'Total runtime:'));
        self::assertStringContainsString('Sniffing', $output);
    }

    #[Test]
    public function itSortsSniffTimesBySlowestFirst(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->measureWallTime(function () use ($report): void {
            $fileReport = new FileReport('file.xml', collectPerformance: true);
            $fileReport->measureSniffer('FastSniff', static fn() => usleep(1_000));
            $fileReport->measureSniffer('SlowSniff', static fn() => usleep(30_000));
            $fileReport->measureSniffer('MediumSniff', static fn() => usleep(10_000));
            $report->addFileReport($fileReport);
        });

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
        $report->measureWallTime(function () use ($report): void {
            $fileReport = new FileReport('file.xml', collectPerformance: true);
            $fileReport->measureSniffer('SniffA', static fn() => usleep(1_000));
            $fileReport->measureSniffer('SniffB', static fn() => usleep(1_000));
            $report->addFileReport($fileReport);
        });

        $output = $reporter->generate($report);

        self::assertMatchesRegularExpression(
            '/^ SniffA +\d+\.\d{3}s \( *\d+\.\d%\) *$/m',
            $output,
        );
    }

    #[Test]
    public function itDisplaysFixingTimeAndPercentage(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: true);

        $report = new Report();
        $report->measureWallTime(function () use ($report): void {
            $fileReport = new FileReport('file.xml', collectPerformance: true);
            $fileReport->measureSniffer('SniffA', static fn() => usleep(1_000));
            $fileReport->measureFixing(
                fn() => $fileReport->measureFixer('SniffA', static fn() => usleep(1_000))
            );
            $report->addFileReport($fileReport);
        });

        $output = $reporter->generate($report);

        self::assertMatchesRegularExpression(
            '/^ SniffA +\d+\.\d{3}s \( *\d+\.\d%\) +\d+\.\d{3}s \( *\d+\.\d%\) *$/m',
            $output,
        );
    }

    #[Test]
    public function itDoesNotShowPerformanceWhenDisabled(): void
    {
        $reporter = new ConsoleReporter(useColors: false, showPerformance: false);

        $report = new Report();

        $fileReport = new FileReport('file.xml');
        $fileReport->measureSniffer('SniffA', static fn() => null);

        $report->addFileReport($fileReport);

        $output = $reporter->generate($report);

        self::assertStringNotContainsString('PERFORMANCE', $output);
    }
}
