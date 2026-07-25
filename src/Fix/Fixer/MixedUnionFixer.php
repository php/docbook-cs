<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class MixedUnionFixer implements Fixer
{
    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $range = $violation->rangeOne();
        if ($range->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (
            $violation->replacement !== '<type>mixed</type>'
            || preg_match('/^<type\b[^>]*\bclass\s*=\s*(["\'])union\1[^>]*>.*<\/type>$/is', $range->content) !== 1
        ) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange($violation, $range, $violation->replacement);
    }
}
