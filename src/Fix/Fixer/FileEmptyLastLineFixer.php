<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class FileEmptyLastLineFixer implements Fixer
{
    private const string LINE_ENDINGS_PATTERN = '/^[\r\n]+$/D';
    private const string UNTERMINATED_LINE_PATTERN = '/^[^\r\n]+$/D';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();
        $affectedContent = $affectedRange->content;

        if ($affectedContent === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (preg_match(self::LINE_ENDINGS_PATTERN, $affectedContent)) {
            return Fix::fromViolationAndRange($violation, $affectedRange, "\n");
        }

        if (!preg_match(self::UNTERMINATED_LINE_PATTERN, $affectedContent)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange(
            $violation,
            $affectedRange,
            $affectedContent . "\n",
        );
    }
}
