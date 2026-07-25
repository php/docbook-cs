<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class TrailingWhitespaceFixer implements Fixer
{
    private const string WHITESPACE_PATTERN = '/^[ \t]+$/';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();

        if ($affectedRange->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::WHITESPACE_PATTERN, $affectedRange->content)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange($violation, $affectedRange, '');
    }
}
