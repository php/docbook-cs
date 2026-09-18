<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

/** @implements Fixer<string> */
final class MixedUnionFixer implements Fixer
{
    /**
     * @param Violation<string> $violation
     * @throws FixerException
     */
    public function process(Violation $violation): Fix
    {
        $range = $violation->rangeOne();
        if ($range->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (
            $violation->fixerData !== '<type>mixed</type>'
            || preg_match('/^<type\b[^>]*\bclass\s*=\s*(["\'])union\1[^>]*>.*<\/type>$/is', $range->content) !== 1
        ) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange($violation, $range, $violation->fixerData);
    }
}
