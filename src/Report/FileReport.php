<?php

declare(strict_types=1);

namespace DocbookCS\Report;

use DocbookCS\Violation\Severity;
use DocbookCS\Violation\Violation;

final class FileReport
{
    /** @var list<Violation> */
    private array $violations = [];

    public function __construct(
        public readonly string $filePath,
    ) {
    }

    public function addViolation(Violation $violation): void
    {
        $this->violations[] = $violation;
    }

    /** @param list<Violation> $violations */
    public function addViolations(array $violations): void
    {
        foreach ($violations as $violation) {
            $this->addViolation($violation);
        }
    }

    /** @return list<Violation> */
    public function getViolations(): array
    {
        return $this->violations;
    }

    public function getViolationCount(): int
    {
        return count($this->violations);
    }

    public function hasViolations(): bool
    {
        return $this->violations !== [];
    }

    public function getErrorCount(): int
    {
        return array_filter(
            $this->violations,
            static fn(Violation $v): bool => $v->severity === Severity::ERROR,
        ) |> count(...);
    }

    public function getWarningCount(): int
    {
        return array_filter(
            $this->violations,
            static fn(Violation $v): bool => $v->severity === Severity::WARNING,
        ) |> count(...);
    }
}
