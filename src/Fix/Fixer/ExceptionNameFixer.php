<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class ExceptionNameFixer implements Fixer
{
    private const string SOURCE_ELEMENT = 'classname';
    private const string TARGET_ELEMENT = 'exceptionname';

    /** @throws FixerException */
    public function process(Violation $violation): FixPlan
    {
        if (count($violation->affectedRanges) !== 2) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $fixes = [];
        foreach ($violation->affectedRanges as $range) {
            if ($range->content === null) {
                throw FixerException::cannotFixMissingContent();
            }

            if ($range->content !== self::SOURCE_ELEMENT) {
                throw FixerException::cannotFixInvalidContent($violation);
            }

            $fixes[] = Fix::fromViolationAndRange(
                $violation,
                $range,
                self::TARGET_ELEMENT,
            );
        }

        return new FixPlan(...$fixes);
    }
}
