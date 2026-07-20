<?php

declare(strict_types=1);

namespace DocbookCS\Fix;

use DocbookCS\Violation\Violation;

final class FixerException extends \RuntimeException
{
    public static function cannotPersist(string $filePath): self
    {
        return new self(sprintf('Could not write fixed file: %s', $filePath));
    }

    public static function cannotFixMissingContent(): self
    {
        return new self('Violations cannot be content-less when passed to a fixer.');
    }

    public static function cannotFixInvalidContent(Violation $violation): self
    {
        return new self(sprintf(
            'Cannot fix violation %s in %s on line %d because its source content is not valid fixer input.',
            $violation->sniffCode,
            $violation->filePath,
            $violation->line,
        ));
    }

    public static function cannotReadFixedContent(): self
    {
        return new self('Cannot read fixed content when no fix application was attempted.');
    }

    public static function invalidFixedXml(string $filePath): self
    {
        return new self(
            sprintf('Fixers produced invalid XML for %s; no changes were written.', $filePath),
        );
    }

    public static function didNotConverge(string $filePath): self
    {
        return new self(
            sprintf('Fixers did not converge for %s; no changes were written.', $filePath),
        );
    }
}
