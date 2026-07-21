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

        if ($this->showPerformance) {
            $output .= PHP_EOL;
            $output .= $this->buildPerformance($report) . PHP_EOL;
        }

        return $output;
    }

    private function buildSummary(Report $report): string
    {
        $timeLine = sprintf('Total runtime: %.3fs', $report->totalTime);

        $suffix = $this->buildSummarySuffix($this->buildFixSummary($report), $timeLine);

        if ($report->getTotalViolations() === 0) {
            return $this->green(
                sprintf(
                    'OK -- %d file(s) scanned, no violations found.',
                    $report->filesScanned,
                )
            ) . $suffix;
        }

        return $this->red(
            sprintf(
                'FOUND %d violation(s) (%d error(s), %d warning(s)) in %d file(s).',
                $report->getTotalViolations(),
                $report->getTotalErrors(),
                $report->getTotalWarnings(),
                count($report->fileReports),
            )
        ) . $suffix;
    }

    private function buildPerformance(Report $report): string
    {
        $totalTime = $report->totalTime;
        $times = $report->sniffTimes;

        if ($report->fixingTime > 0.0) {
            $times['Fixing'] = $report->fixingTime;
        }

        if ($totalTime <= 0.0 || $times === []) {
            return $this->dim('No performance data available.');
        }

        // Sort slowest first.
        arsort($times);

        $output = $this->bold('PERFORMANCE') . PHP_EOL;
        $output .= str_repeat('-', 40) . PHP_EOL;

        $output .= sprintf(
            ' Total runtime: %.3fs',
            $totalTime
        ) . PHP_EOL . PHP_EOL;

        foreach ($times as $name => $time) {
            $percent = ($time / $totalTime) * 100;

            $output .= sprintf(
                ' %-40s %6.3fs (%5.1f%%)',
                $name,
                $time,
                $percent,
            ) . PHP_EOL;
        }

        return $output;
    }

    private function formatSeverity(Severity $severity): string
    {
        return match ($severity) { // @codeCoverageIgnore
            Severity::ERROR => $this->red(str_pad(Severity::ERROR->name, 7)),
            Severity::WARNING => $this->yellow(str_pad(Severity::WARNING->name, 7)),
            default => $this->dim(str_pad(strtoupper($severity->name), 7)),
        }; // @codeCoverageIgnore
    }

    private function buildSummarySuffix(string $fixSummary, string $timeLine): string
    {
        return ($fixSummary !== '' ? PHP_EOL . $fixSummary : '') . PHP_EOL . $this->dim($timeLine);
    }

    private function buildFixSummary(Report $report): string
    {
        $applied = $report->fixesApplied;
        $skipped = $report->fixesSkipped;

        if ($applied === 0 && $skipped === 0) {
            return '';
        }

        if ($applied === 0) {
            return sprintf('Skipped %d fix(es).', $skipped);
        }

        $summary = sprintf(
            'Applied %d fix(es) in %d file(s) across %d fixing pass(es).',
            $applied,
            $report->filesModified,
            $report->fixPasses,
        );

        return $skipped > 0
            ? $summary . sprintf(' Skipped %d fix(es).', $skipped)
            : $summary;
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
