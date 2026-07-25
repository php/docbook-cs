<?php

declare(strict_types=1);

namespace DocbookCS\Report;

use DocbookCS\Violation\Violation;

final class Report
{
    /** @var array<string, FileReport> */
    private array $fileReports = [];

    private int $filesScanned = 0;

    private float $totalTime = 0.0;

    /** @var array<string, float> */
    private array $sniffTimes = [];

    public function addFileReport(FileReport $fileReport): void
    {
        $this->fileReports[$fileReport->filePath] = $fileReport;
    }

    public function incrementFilesScanned(): void
    {
        $this->filesScanned++;
    }

    public function getFilesScanned(): int
    {
        return $this->filesScanned;
    }

    /** @return array<string, FileReport> */
    public function getFileReports(): array
    {
        return $this->fileReports;
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

    public function addSniffTime(string $sniffClass, float $time): void
    {
        if (!isset($this->sniffTimes[$sniffClass])) {
            $this->sniffTimes[$sniffClass] = 0.0;
        }

        $this->sniffTimes[$sniffClass] += $time;
    }

    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    /** @return array<string, float> */
    public function getSniffTimes(): array
    {
        return $this->sniffTimes;
    }
}
