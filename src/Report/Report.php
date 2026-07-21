<?php

declare(strict_types=1);

namespace DocbookCS\Report;

use DocbookCS\Violation\Violation;

final class Report
{
    /** @var array<string, FileReport> */
    public private(set) array $fileReports = [];

    public private(set) int $filesScanned = 0;

    public private(set) float $totalTime = 0.0;

    /** @var array<string, float> */
    public private(set) array $sniffTimes = [];

    public private(set) int $filesModified = 0;

    public private(set) int $fixesApplied = 0;

    public private(set) int $fixesSkipped = 0;

    public private(set) int $fixPasses = 0;

    public private(set) float $fixingTime = 0.0;

    public function addFileReport(FileReport $fileReport): void
    {
        $this->fileReports[$fileReport->filePath] = $fileReport;
    }

    public function incrementFilesScanned(): void
    {
        $this->filesScanned++;
    }

    public function getTotalViolations(): int
    {
        $total = 0;
        foreach ($this->fileReports as $fr) {
            $total += $fr->getViolationCount();
        }

        return $total;
    }

    public function getTotalErrors(): int
    {
        $total = 0;
        foreach ($this->fileReports as $fr) {
            $total += $fr->getErrorCount();
        }

        return $total;
    }

    public function getTotalWarnings(): int
    {
        $total = 0;
        foreach ($this->fileReports as $fr) {
            $total += $fr->getWarningCount();
        }

        return $total;
    }

    public function hasViolations(): bool
    {
        return $this->getTotalViolations() > 0;
    }

    /** @return list<Violation> */
    public function getAllViolations(): array
    {
        $all = [];
        foreach ($this->fileReports as $fileReport) {
            foreach ($fileReport->getViolations() as $violation) {
                $all[] = $violation;
            }
        }

        return $all;
    }

    public function setTotalTime(float $time): void
    {
        $this->totalTime = $time;
    }

    public function addSniffTime(string $sniffCode, float $time): void
    {
        if (!isset($this->sniffTimes[$sniffCode])) {
            $this->sniffTimes[$sniffCode] = 0.0;
        }

        $this->sniffTimes[$sniffCode] += $time;
    }

    public function addFixTime(float $time): void
    {
        $this->fixingTime += $time;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureFixing(callable $operation): mixed
    {
        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->addFixTime(microtime(true) - $start);
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureSniff(string $sniffCode, callable $operation): mixed
    {
        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->addSniffTime($sniffCode, microtime(true) - $start);
        }
    }

    public function recordModifiedFile(): void
    {
        $this->filesModified++;
    }

    public function recordFixPass(int $applied, int $skipped): void
    {
        $this->fixesApplied += $applied;
        $this->fixesSkipped += $skipped;
        $this->fixPasses++;
    }
}
