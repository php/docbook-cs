<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Runner;

use DocbookCS\Config\SniffEntry;
use DocbookCS\Report\Report;
use DocbookCS\Runner\RunCoordinator;
use DocbookCS\Runner\RunMode;
use DocbookCS\Runner\RunPlan;
use DocbookCS\Sniff\SimparaSniff;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RunReportingTest extends TestCase
{
    #[Test] // TODO: should be integration
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

            $report = new RunCoordinator()->run($plan);

            self::assertSame('<root><simpara>Text</simpara></root>', file_get_contents($filePath));
            self::assertSame(1, $report->filesModified);
            self::assertSame(1, $report->fixesApplied);
            self::assertSame(0, $report->fixesSkipped);
            self::assertSame(1, $report->fixPasses);
            self::assertFalse($report->hasViolations());
            self::assertArrayHasKey(SimparaSniff::getCode(), $report->sniffTimes);
            self::assertGreaterThan(0.0, $report->fixingTime);
        } finally {
            @unlink($filePath);
        }
    }
}
