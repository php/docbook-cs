<?php

declare(strict_types=1);

namespace DocbookCS\Report;

use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final class FileReport
{
    public private(set) float $totalSniffingTime = 0.0;

    public private(set) float $totalFixingTime = 0.0;

    /** @var array<string, float> */
    public private(set) array $sniffingTimes = [];

    /** @var array<string, float> */
    public private(set) array $fixingTimes = [];

    public private(set) int $fixingPasses = 0;

    public private(set) bool $changed = false;

    /** @var list<Violation> */
    public private(set) array $foundViolations;

    /** @var list<Violation> */
    public private(set) array $finalViolations;

    public function __construct(
        public readonly string $filePath,
        private readonly bool $collectPerformance = false,
    ) {
    }

    public function markChanged(): void
    {
        $this->changed = true;
    }

    /** @throws ReportException if found violations were already added */
    public function addFailedViolation(Violation $violation): void
    {
        if (isset($this->foundViolations)) {
            throw ReportException::foundViolationsAlreadyAdded($this->filePath);
        }

        $this->foundViolations = [$violation];
        $this->finalViolations = [$violation];
    }

    /**
     * @param list<Violation> $violations
     * @throws ReportException if found violations were already added
     */
    public function addFoundViolations(array $violations): void
    {
        if (isset($this->foundViolations)) {
            throw ReportException::foundViolationsAlreadyAdded($this->filePath);
        }

        $this->foundViolations = $violations;
        $this->finalViolations = $violations;
    }

    public function getFoundViolationCount(): int
    {
        return isset($this->foundViolations) ? count($this->foundViolations) : 0;
    }

    /**
     * @param list<Violation> $violations
     * @throws ReportException if found violations were not added
     */
    public function addFinalViolations(array $violations): void
    {
        if (!isset($this->foundViolations)) {
            throw ReportException::cannotSetFinalViolationsBeforeFoundViolations($this->filePath);
        }

        $this->finalViolations = $violations;
    }

    public function hasFinalViolations(): bool
    {
        return isset($this->finalViolations) && $this->finalViolations !== [];
    }

    public function getFinalViolationCount(): int
    {
        return isset($this->finalViolations) ? count($this->finalViolations) : 0;
    }

    public function getAppliedFixesCount(): int
    {
        return $this->fixingPasses > 0
            ? max(0, $this->getFoundViolationCount() - $this->getFinalViolationCount())
            : 0;
    }

    public function getSkippedFixesCount(): int
    {
        return $this->fixingPasses > 0
            ? $this->getFinalViolationCount()
            : 0;
    }

    public function recordFixingPass(): void
    {
        $this->fixingPasses++;
    }

    public function getErrorCount(): int
    {
        return $this->countSeverity(Severity::ERROR);
    }

    public function getWarningCount(): int
    {
        return $this->countSeverity(Severity::WARNING);
    }

    public function getInfoCount(): int
    {
        return $this->countSeverity(Severity::INFO);
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureFixing(callable $operation): mixed
    {
        if (!$this->collectPerformance) {
            return $operation();
        }

        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->totalFixingTime += microtime(true) - $start;
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureFixer(string $sniffCode, callable $operation): mixed
    {
        if (!$this->collectPerformance) {
            return $operation();
        }

        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->fixingTimes[$sniffCode] ??= 0.0;
            $this->fixingTimes[$sniffCode] += microtime(true) - $start;
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureSniffing(callable $operation): mixed
    {
        if (!$this->collectPerformance) {
            return $operation();
        }

        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->totalSniffingTime += microtime(true) - $start;
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function measureSniffer(string $sniffCode, callable $operation): mixed
    {
        if (!$this->collectPerformance) {
            return $operation();
        }

        $start = microtime(true);

        try {
            return $operation();
        } finally {
            $this->sniffingTimes[$sniffCode] ??= 0.0;
            $this->sniffingTimes[$sniffCode] += microtime(true) - $start;
        }
    }

    private function countSeverity(Severity $severity): int
    {
        if (!isset($this->finalViolations)) {
            return 0;
        }

        return array_filter(
            $this->finalViolations,
            static fn(Violation $violation): bool => $violation->severity === $severity,
        ) |> count(...);
    }
}
