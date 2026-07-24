<?php

declare(strict_types=1);

namespace DocbookCS\Report\Reporter;

use DocbookCS\RelativePath;
use DocbookCS\Report\Report;
use DocbookCS\Violation\Severity;

final class ConsoleReporter implements ReporterInterface
{
    private bool $useColors;
    private bool $showPerformance;

    public function __construct(bool $useColors = true, bool $showPerformance = false)
    {
        $this->useColors = $useColors;
        $this->showPerformance = $showPerformance;
    }

    public function generate(Report $report): string
    {
        $output = '';

        foreach ($report->fileReports as $fileReport) {
            if (!$fileReport->hasFinalViolations()) {
                continue;
            }

            $filePath = RelativePath::fromWorkingDirectory($fileReport->filePath);

            $output .= PHP_EOL;
            $output .= $this->bold('FILE: ' . $filePath) . PHP_EOL;
            $output .= str_repeat('-', min(80, 6 + strlen($filePath))) . PHP_EOL;

            foreach ($fileReport->finalViolations as $violation) {
                $output .= sprintf(
                    ' %4d | %s | %s | %s',
                    $violation->rangeOne()->line,
                    $this->formatSeverity($violation->severity),
                    $this->dim($violation->sniffCode),
                    $violation->message,
                ) . PHP_EOL;
            }

            $output .= str_repeat('-', min(80, 6 + strlen($filePath))) . PHP_EOL;
        }

        $output .= PHP_EOL;
        $output .= $this->buildSummary($report) . PHP_EOL;

        $fixingStatistics = $this->buildFixingStatistics($report);
        if ($fixingStatistics !== null) {
            $output .= PHP_EOL;
            $output .= $fixingStatistics . PHP_EOL;
        }

        if ($this->showPerformance) {
            $output .= PHP_EOL;
            $output .= $this->buildPerformance($report) . PHP_EOL;
        }

        return $output;
    }

    private function buildSummary(Report $report): string
    {
        $timeLine = sprintf('Total runtime: %.3fs', $report->totalTime);

        if ($report->getTotalFinalViolationCount() === 0) {
            $outcome = $report->hasFixingResults()
                ? 'no violations remaining.'
                : 'no violations found.';

            return $this->green(
                sprintf(
                    'OK -- %d file(s) scanned, %s',
                    $report->getScannedFilesCount(),
                    $outcome,
                )
            ) . PHP_EOL . $this->dim($timeLine);
        }

        // todo: how about info level?
        return $this->red(
            sprintf(
                '%s %d violation(s) (%d error(s), %d warning(s)) in %d file(s).',
                $report->hasFixingResults() ? 'REMAINING' : 'FOUND',
                $report->getTotalFinalViolationCount(),
                $report->getTotalErrorLevelViolationCount(),
                $report->getTotalWarningLevelViolationCount(),
                $report->getViolatingFilesCount(),
            )
        ) . PHP_EOL . $this->dim($timeLine);
    }

    private function buildFixingStatistics(Report $report): ?string
    {
        if (!$report->hasFixingResults()) {
            return null;
        }

        $statistics = [
            'Files changed' => $report->getChangedFilesCount(),
            'Fixes applied' => $report->getAppliedFixesCount(),
            'Fixes skipped' => $report->getSkippedFixesCount(),
            'Fixing passes' => $report->getFixingPassesCount(),
        ];

        $lines = [$this->bold('FIXING'), str_repeat('-', 40)];

        foreach ($statistics as $name => $count) {
            $lines[] = sprintf(' %-40s %d', $name, $count);
        }

        return implode(PHP_EOL, $lines);
    }

    private function buildPerformance(Report $report): string
    {
        $totalTime = $report->totalTime;
        $rows = $this->collectPerformanceRows($report);

        if ($totalTime <= 0.0 || $rows === []) {
            return $this->dim('No performance data available.');
        }

        $nameWidth = 40;
        foreach (array_keys($rows) as $sniffCode) {
            $nameWidth = max($nameWidth, strlen($sniffCode));
        }

        $header = sprintf(' %-*s  %16s  %16s', $nameWidth, '', 'Sniffing', 'Fixing');
        $lines = [$this->bold('PERFORMANCE'), str_repeat('-', strlen($header)), $this->bold($header)];

        foreach ($rows as $sniffCode => $times) {
            $lines[] = sprintf(
                ' %-*s  %16s  %16s',
                $nameWidth,
                $sniffCode,
                $this->formatPerformanceCell($times['sniffing'], $totalTime),
                $this->formatPerformanceCell($times['fixing'], $totalTime),
            );
        }

        return implode(PHP_EOL, $lines);
    }

    /** @return array<string, array{sniffing: ?float, fixing: ?float}> */
    private function collectPerformanceRows(Report $report): array
    {
        $sniffingTimes = $report->getSniffingTimes();
        $fixingTimes = $report->getFixingTimes();
        $rows = [];

        foreach ($sniffingTimes + $fixingTimes as $sniffCode => $_) {
            $rows[$sniffCode] = [
                'sniffing' => $sniffingTimes[$sniffCode] ?? null,
                'fixing' => $fixingTimes[$sniffCode] ?? null,
            ];
        }

        // Sort slowest first.
        uasort(
            $rows,
            static fn(array $left, array $right): int =>
                (($right['sniffing'] ?? 0.0) + ($right['fixing'] ?? 0.0))
                <=> (($left['sniffing'] ?? 0.0) + ($left['fixing'] ?? 0.0)),
        );

        return $rows;
    }

    private function formatSeverity(Severity $severity): string
    {
        return match ($severity) { // @codeCoverageIgnore
            Severity::ERROR => $this->red(str_pad(Severity::ERROR->name, 7)),
            Severity::WARNING => $this->yellow(str_pad(Severity::WARNING->name, 7)),
            default => $this->dim(str_pad(strtoupper($severity->name), 7)),
        }; // @codeCoverageIgnore
    }

    private function formatPerformanceCell(?float $time, float $totalTime): string
    {
        return $time === null
            ? ''
            : sprintf('%6.3fs (%5.1f%%)', $time, ($time / $totalTime) * 100);
    }

    private function bold(string $text): string
    {
        return $this->wrap($text, '1');
    }

    private function dim(string $text): string
    {
        return $this->wrap($text, '2');
    }

    private function red(string $text): string
    {
        return $this->wrap($text, '31');
    }

    private function yellow(string $text): string
    {
        return $this->wrap($text, '33');
    }

    private function green(string $text): string
    {
        return $this->wrap($text, '32');
    }

    private function wrap(string $text, string $code): string
    {
        if (!$this->useColors) {
            return $text;
        }

        return "\033[{$code}m{$text}\033[0m";
    }
}
