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
            if (!$fileReport->hasViolations()) {
                continue;
            }

            $filePath = RelativePath::fromWorkingDirectory($fileReport->filePath);

            $output .= PHP_EOL;
            $output .= $this->bold('FILE: ' . $filePath) . PHP_EOL;
            $output .= str_repeat('-', min(80, 6 + strlen($filePath))) . PHP_EOL;

            foreach ($fileReport->getViolations() as $violation) {
                $output .= sprintf(
                    ' %4d | %s | %s | %s',
                    $violation->line,
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

        if ($report->getTotalViolations() === 0) {
            return $this->green(
                sprintf(
                    'OK -- %d file(s) scanned, no violations found.',
                    $report->filesScanned,
                )
            ) . PHP_EOL . $this->dim($timeLine);
        }

        return $this->red(
            sprintf(
                '%s %d violation(s) (%d error(s), %d warning(s)) in %d file(s).',
                $this->hasFixingStatistics($report) ? 'REMAINING' : 'FOUND',
                $report->getTotalViolations(),
                $report->getTotalErrors(),
                $report->getTotalWarnings(),
                count($report->fileReports),
            )
        ) . PHP_EOL . $this->dim($timeLine);
    }

    private function buildFixingStatistics(Report $report): ?string
    {
        if (!$this->hasFixingStatistics($report)) {
            return null;
        }

        $statistics = [
            'Files changed' => $report->filesChanged,
            'Fixes applied' => $report->fixesApplied,
            'Fixes skipped' => $report->fixesSkipped,
            'Fixing passes' => $report->fixingPasses,
        ];
        $lines = [$this->bold('FIXING'), str_repeat('-', 40)];

        foreach ($statistics as $name => $count) {
            $lines[] = sprintf(' %-40s %d', $name, $count);
        }

        return implode(PHP_EOL, $lines);
    }

    private function hasFixingStatistics(Report $report): bool
    {
        return $report->fixesApplied > 0 || $report->fixesSkipped > 0;
    }

    private function buildPerformance(Report $report): string
    {
        $totalTime = $report->totalTime;
        $sniffTimes = $report->sniffTimes;

        if ($totalTime <= 0.0 || ($sniffTimes === [] && $report->fixingTime <= 0.0)) {
            return $this->dim('No performance data available.');
        }

        // Sort slowest first.
        arsort($sniffTimes);

        $lines = [
            $this->bold('PERFORMANCE'),
            str_repeat('-', 40),
            '',
        ];

        if ($sniffTimes !== []) {
            $lines[] = $this->bold('Sniffing:');

            foreach ($sniffTimes as $name => $time) {
                $lines[] = $this->formatPerformanceRow($name, $time, $totalTime);
            }
        }

        if ($report->fixingTime > 0.0) {
            if ($sniffTimes !== []) {
                $lines[] = '';
            }

            $lines[] = $this->bold('Fixing:');
            $lines[] = $this->formatPerformanceRow('Total', $report->fixingTime, $totalTime);
        }

        return implode(PHP_EOL, $lines);
    }

    private function formatSeverity(Severity $severity): string
    {
        return match ($severity) { // @codeCoverageIgnore
            Severity::ERROR => $this->red(str_pad(Severity::ERROR->name, 7)),
            Severity::WARNING => $this->yellow(str_pad(Severity::WARNING->name, 7)),
            default => $this->dim(str_pad(strtoupper($severity->name), 7)),
        }; // @codeCoverageIgnore
    }

    private function formatPerformanceRow(string $name, float $time, float $totalTime): string
    {
        return sprintf(' %-40s %6.3fs (%5.1f%%)', $name, $time, ($time / $totalTime) * 100);
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
