<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class MixedIndentationFixer implements Fixer
{
    private const string INDENTATION_PATTERN = '/^[ \t]+$/';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();

        if ($affectedRange->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (
            !preg_match(self::INDENTATION_PATTERN, $affectedRange->content)
            || !str_contains($affectedRange->content, ' ')
            || !str_contains($affectedRange->content, "\t")
        ) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange(
            $violation,
            $affectedRange,
            str_replace("\t", ' ', $affectedRange->content),
        );
    }
}
