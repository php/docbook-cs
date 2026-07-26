<?php

declare(strict_types=1);

namespace DocbookCS\Report;

final class ReportException extends \RuntimeException
{
    public static function foundViolationsAlreadyAdded(string $filePath): self
    {
        return new self(sprintf('Found violations were already added for "%s".', $filePath));
    }

    public static function cannotSetFinalViolationsBeforeFoundViolations(string $filePath): self
    {
        return new self(sprintf('Found violations were not added for "%s".', $filePath));
    }
}
