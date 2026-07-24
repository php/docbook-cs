<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Report\Reporter;

use DocbookCS\RelativePath;
use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Reporter\CheckstyleReporter;
use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(CheckstyleReporter::class),
    CoversClass(FileReport::class),
    CoversClass(Report::class),
    CoversClass(Violation::class),
    //
    UsesClass(RelativePath::class),
]
final class CheckstyleReporterTest extends TestCase
{
    private CheckstyleReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new CheckstyleReporter();
    }

    private function createViolation(
        string $message = 'Some problem',
        int $line = 1,
        string $sniffCode = 'DocbookCS.Test',
        Severity $severity = Severity::ERROR,
    ): Violation {
        return new Violation($sniffCode, 'filepath.xml', $line, $message, $severity);
    }

    private function parseOutput(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        return $dom;
    }

    #[Test]
    public function itReturnsValidXml(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($output));
    }

    #[Test]
    public function itContainsCheckstyleRootWithVersionAttribute(): void
    {
        $report = new Report();
        $dom = $this->parseOutput($this->reporter->generate($report));

        self::assertSame('checkstyle', $dom->documentElement?->nodeName);
        self::assertSame('3.0', $dom->documentElement->getAttribute('version'));
    }

    #[Test]
    public function itReturnsUtf8EncodedXml(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertStringContainsString('encoding="UTF-8"', $output);
    }

    #[Test]
    public function itProducesNoFileNodesForEmptyReport(): void
    {
        $report = new Report();

        $dom = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(0, $dom->getElementsByTagName('file')->length);
    }

    #[Test]
    public function itSkipsFilesWithNoViolations(): void
    {
        $report = new Report();
        $report->addFileReport(new FileReport('clean.xml'));

        $dom = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(0, $dom->getElementsByTagName('file')->length);
    }

    #[Test]
    public function itIncludesFileNodeWithNameAttribute(): void
    {
        $fileReport = new FileReport('src/broken.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        $fileNodes = $dom->getElementsByTagName('file');
        self::assertSame(1, $fileNodes->length);
        self::assertSame('src/broken.xml', $fileNodes->item(0)?->getAttribute('name'));
    }

    #[Test]
    public function itRendersAbsoluteFilePathRelativeToWorkingDirectory(): void
    {
        $fileReport = new FileReport((getcwd() ?: '') . '/src/broken.xml');
        $fileReport->addViolation($this->createViolation());

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(
            'src/broken.xml',
            $dom->getElementsByTagName('file')->item(0)?->getAttribute('name'),
        );
    }

    #[Test]
    public function itSetsLineAttribute(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(line: 42));

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        $errorNode = $dom->getElementsByTagName('error')->item(0);
        self::assertSame('42', $errorNode?->getAttribute('line'));
    }

    #[Test]
    public function itSetsSeverityAttribute(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(severity: Severity::WARNING));

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        $errorNode = $dom->getElementsByTagName('error')->item(0);
        self::assertSame('warning', $errorNode?->getAttribute('severity'));
    }

    #[Test]
    public function itSetsMessageAttribute(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(message: 'Use <simpara> instead'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        $errorNode = $dom->getElementsByTagName('error')->item(0);
        self::assertSame('Use <simpara> instead', $errorNode?->getAttribute('message'));
    }

    #[Test]
    public function itSetsSourceAttribute(): void
    {
        $fileReport = new FileReport('file.xml');
        $fileReport->addViolation($this->createViolation(sniffCode: 'DocbookCS.ExceptionName'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $dom = $this->parseOutput($this->reporter->generate($report));

        $errorNode = $dom->getElementsByTagName('error')->item(0);
        self::assertSame('DocbookCS.ExceptionName', $errorNode?->getAttribute('source'));
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

        $dom = $this->parseOutput($this->reporter->generate($report));

        self::assertSame(3, $dom->getElementsByTagName('error')->length);
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

        $dom = $this->parseOutput($this->reporter->generate($report));

        $fileNodes = $dom->getElementsByTagName('file');
        self::assertSame(2, $fileNodes->length);
        self::assertSame('first.xml', $fileNodes->item(0)?->getAttribute('name'));
        self::assertSame('second.xml', $fileNodes->item(1)?->getAttribute('name'));
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

        $dom = $this->parseOutput($this->reporter->generate($report));

        $fileNodes = $dom->getElementsByTagName('file');
        self::assertSame(1, $fileNodes->length);
        self::assertSame('dirty.xml', $fileNodes->item(0)?->getAttribute('name'));
    }

    #[Test]
    public function itEscapesSpecialCharactersInMessage(): void
    {
        $fileReport = new FileReport('escape.xml');
        $fileReport->addViolation($this->createViolation(message: 'Use "quotes" & <tags>'));

        $report = new Report();
        $report->addFileReport($fileReport);

        $output = $this->reporter->generate($report);

        $dom = new \DOMDocument();
        self::assertTrue($dom->loadXML($output));

        $errorNode = $dom->getElementsByTagName('error')->item(0);
        self::assertSame('Use "quotes" & <tags>', $errorNode?->getAttribute('message'));
    }

    #[Test]
    public function itReturnsNonEmptyStringForEmptyReport(): void
    {
        $report = new Report();

        $output = $this->reporter->generate($report);

        self::assertNotEmpty($output);
    }
}
