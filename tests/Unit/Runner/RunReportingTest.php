<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\SniffEntry;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RunReportingTest extends TestCase
{
    #[Test]
    public function itReportsFixingOutcomeAndPerformance(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-reporting-');
        self::assertIsString($filePath);
        file_put_contents($filePath, '<root><para>Text</para></root>');

        try {
            $plan = new RunPlan(
                mode: RunMode::Fix,
                sniffs: [new SniffEntry(SimparaSniff::class)],
                targets: [$filePath => null],
                entities: [],
            );

            $report = new RunCoordinator(collectPerformance: true)->runWithMetrics($plan);

            self::assertSame('<root><simpara>Text</simpara></root>', file_get_contents($filePath));
            self::assertSame(1, $report->getChangedFilesCount());
            self::assertSame(1, $report->getFoundViolationsCount());
            self::assertSame(1, $report->getAppliedFixesCount());
            self::assertSame(0, $report->getSkippedFixesCount());
            self::assertSame(1, $report->getFixingPassesCount());
            self::assertFalse($report->hasFinalViolations());
            self::assertArrayHasKey(SimparaSniff::getCode(), $report->getSniffingTimes());
            self::assertArrayHasKey(SimparaSniff::getCode(), $report->getFixingTimes());
            self::assertGreaterThanOrEqual(
                array_sum($report->getSniffingTimes()),
                $report->getTotalSniffingTime(),
            );
            self::assertGreaterThan(0.0, $report->getTotalFixingTime());
        } finally {
            @unlink($filePath);
        }
    }

    #[Test]
    public function itReportsInitialAndFinalViolationsAfterFixingShiftsTheSource(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'docbook-cs-reporting-');
        self::assertIsString($filePath);
        file_put_contents($filePath, '<root><para>Text</para><bad/></root>');

        $badElementSniff = new class () extends AbstractSniff {
            public static function getCode(): string
            {
                return 'Test.BadElement';
            }

            public function process(\DOMDocument $document, File $file): array
            {
                $offset = strpos($file->content, '<bad/>');
                if ($offset === false) {
                    return [];
                }

                return [$this->createViolation(
                    $file->path,
                    'Bad element.',
                    [new SourceRange(1, $offset, $offset + strlen('<bad/>'), '<bad/>')],
                )];
            }
        };

        try {
            $report = new RunCoordinator()->runWithMetrics(new RunPlan(
                mode: RunMode::Fix,
                sniffs: [
                    new SniffEntry(SimparaSniff::class),
                    new SniffEntry($badElementSniff::class),
                ],
                targets: [$filePath => null],
                entities: [],
            ));

            $fileReport = $report->fileReports[$filePath];

            self::assertSame(2, $fileReport->getFoundViolationCount());
            self::assertSame(SimparaSniff::getCode(), $fileReport->foundViolations[0]->sniffCode);
            self::assertSame(1, $fileReport->getFinalViolationCount());
            self::assertSame('Test.BadElement', $fileReport->finalViolations[0]->sniffCode);
            self::assertGreaterThan(
                $fileReport->foundViolations[1]->rangeOne()->beginOffset,
                $fileReport->finalViolations[0]->rangeOne()->beginOffset,
            );
            self::assertSame(1, $report->getAppliedFixesCount());
            self::assertSame(1, $report->getSkippedFixesCount());
        } finally {
            @unlink($filePath);
        }
    }
}
