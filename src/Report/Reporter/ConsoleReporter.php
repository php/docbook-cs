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

        if ($this->showPerformance) {
            $output .= PHP_EOL;
            $output .= $this->buildPerformance($report) . PHP_EOL;
        }

        return $output;
    }

    private function buildSummary(Report $report): string
    {
        $lines = $report->hasFixingResults()
            ? $this->buildFixingOutcome($report)
            : [$this->buildSniffingOutcome($report)];

        $lines[] = $this->dim(sprintf('Total runtime: %.3fs', $report->totalTime));

        return implode(PHP_EOL, $lines);
    }

    /** @return non-empty-list<string> */
    private function buildFixingOutcome(Report $report): array
    {
        $lines = [
            $this->green($this->formatFixedSummary($report)),
        ];

        if ($report->hasFinalViolations()) {
            $lines[] = $this->red($this->formatFinalSummary($report, 'REMAINING'));
        }

        return $lines;
    }

    private function buildSniffingOutcome(Report $report): string
    {
        if ($report->hasFinalViolations()) {
            return $this->red($this->formatFinalSummary($report, 'FOUND'));
        }

        return $this->green(sprintf(
            'OK -- %s scanned, no violations found.',
            $this->formatCount('file', $report->getScannedFilesCount()),
        ));
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
        return match ($severity) {
            Severity::ERROR => $this->red(str_pad(Severity::ERROR->name, 7)),
            Severity::WARNING => $this->yellow(str_pad(Severity::WARNING->name, 7)),
            default => $this->dim(str_pad(strtoupper($severity->name), 7)),
        };
    }

    private function formatPerformanceCell(?float $time, float $totalTime): string
    {
        return $time === null
            ? ''
            : sprintf('%6.3fs (%5.1f%%)', $time, ($time / $totalTime) * 100);
    }

    private function formatFixedSummary(Report $report): string
    {
        $files = $report->getChangedFilesCount();
        $passes = $report->getFixingPassesCount();
        $ending = $report->hasFinalViolations()
            ? '.'
            : ', no violations remaining.';

        return sprintf(
            'FIXED %s [%s, %s] in %s%s%s',
            $this->formatCount('violation', $report->getAppliedFixesCount()),
            $this->formatCount('error', $report->getFixedErrorCount()),
            $this->formatCount('warning', $report->getFixedWarningCount()),
            $this->formatCount('file', $files),
            $files > 0 && $passes > $files
                ? sprintf(' (%s passes)', number_format($passes))
                : '',
            $ending,
        );
    }

    private function formatFinalSummary(Report $report, string $label): string
    {
        // todo: how about info level?
        return sprintf(
            '%s %s [%s, %s] in %s.',
            $label,
            $this->formatCount('violation', $report->getTotalFinalViolationCount()),
            $this->formatCount('error', $report->getTotalErrorLevelViolationCount()),
            $this->formatCount('warning', $report->getTotalWarningLevelViolationCount()),
            $this->formatCount('file', $report->getViolatingFilesCount()),
        );
    }

    private function formatCount(string $singular, int $count): string
    {
        $suffix = $count === 1 ? $singular : $singular . 's';

        return sprintf('%s %s', number_format($count), $suffix);
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
