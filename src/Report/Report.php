<?php

declare(strict_types=1);

namespace DocbookCS\Report;

use DocbookCS\Violation\Violation;

final class Report
{
    public private(set) float $totalTime = 0.0;

    /** @var array<string, FileReport> */
    public private(set) array $fileReports = [];

    public function __construct(private readonly bool $collectPerformance = false)
    {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureWallTime(callable $operation): mixed
    {
        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->totalTime = microtime(true) - $start;
        }
    }

    public function newFileReport(string $filePath): FileReport
    {
        return $this->fileReports[$filePath] = new FileReport($filePath, $this->collectPerformance);
    }

    public function addFileReport(FileReport $fileReport): void
    {
        $this->fileReports[$fileReport->filePath] = $fileReport;
    }

    public function getScannedFilesCount(): int
    {
        return count($this->fileReports);
    }

    public function getViolatingFilesCount(): int
    {
        return array_filter(
            $this->fileReports,
            static fn(FileReport $fileReport): bool => $fileReport->hasFinalViolations(),
        ) |> count(...);
    }

    public function getChangedFilesCount(): int
    {
        return array_filter(
            $this->fileReports,
            static fn(FileReport $fileReport): bool => $fileReport->changed,
        ) |> count(...);
    }

    public function getFoundViolationsCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getFoundViolationCount(),
            $this->fileReports,
        ));
    }

    public function getAppliedFixesCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getAppliedFixesCount(),
            $this->fileReports,
        ));
    }

    public function getSkippedFixesCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getSkippedFixesCount(),
            $this->fileReports,
        ));
    }

    public function getFixedErrorCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getFixedErrorCount(),
            $this->fileReports,
        ));
    }

    public function getFixedWarningCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getFixedWarningCount(),
            $this->fileReports,
        ));
    }

    public function hasFixingResults(): bool
    {
        return array_any($this->fileReports, static fn(FileReport $fileReport): bool => $fileReport->fixingPasses > 0);
    }

    public function getFixingPassesCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->fixingPasses,
            $this->fileReports,
        ));
    }

    public function getTotalFixingTime(): float
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): float => $fileReport->totalFixingTime,
            $this->fileReports,
        ));
    }

    public function getTotalSniffingTime(): float
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): float => $fileReport->totalSniffingTime,
            $this->fileReports,
        ));
    }

    /** @return array<string, float> */
    public function getFixingTimes(): array
    {
        $fixingTimes = [];

        foreach ($this->fileReports as $fileReport) {
            foreach ($fileReport->fixingTimes as $sniffCode => $time) {
                $fixingTimes[$sniffCode] ??= 0.0;
                $fixingTimes[$sniffCode] += $time;
            }
        }

        return $fixingTimes;
    }

    /** @return array<string, float> */
    public function getSniffingTimes(): array
    {
        $sniffingTimes = [];

        foreach ($this->fileReports as $fileReport) {
            foreach ($fileReport->sniffingTimes as $sniffCode => $time) {
                $sniffingTimes[$sniffCode] ??= 0.0;
                $sniffingTimes[$sniffCode] += $time;
            }
        }

        return $sniffingTimes;
    }

    public function getTotalFinalViolationCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getFinalViolationCount(),
            $this->fileReports,
        ));
    }

    public function getTotalErrorLevelViolationCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getErrorCount(),
            $this->fileReports,
        ));
    }

    public function getTotalWarningLevelViolationCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getWarningCount(),
            $this->fileReports,
        ));
    }

    /** @api not implemented */
    public function getTotalInfoLevelViolationCount(): int
    {
        return array_sum(array_map(
            static fn(FileReport $fileReport): int => $fileReport->getInfoCount(),
            $this->fileReports,
        ));
    }

    public function hasFinalViolations(): bool
    {
        return $this->getTotalFinalViolationCount() > 0;
    }

    /** @return list<Violation> */
    public function getAllViolations(): array
    {
        $violations = [];

        foreach ($this->fileReports as $fileReport) {
            if (!$fileReport->hasFinalViolations()) {
                continue;
            }

            array_push($violations, ...$fileReport->finalViolations);
        }

        return $violations;
    }
}
