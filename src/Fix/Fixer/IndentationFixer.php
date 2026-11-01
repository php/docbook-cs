<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

/** @implements Fixer<int> */
final class IndentationFixer implements Fixer
{
    private const string INDENTATION_PATTERN = '/^[ \t]*$/D';

    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();

        if ($affectedRange->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        $expectedDepth = $violation->fixerData;

        if (
            count($violation->affectedRanges) !== 1
            || !preg_match(self::INDENTATION_PATTERN, $affectedRange->content)
            || !is_int($expectedDepth)
            || $expectedDepth < 0
        ) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $replacement = str_repeat(' ', $expectedDepth);

        if ($affectedRange->content === $replacement) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange($violation, $affectedRange, $replacement);
    }
}
