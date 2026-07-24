<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class SimparaFixer implements Fixer
{
    private const string SOURCE_ELEMENT = 'para';
    private const string TARGET_ELEMENT = 'simpara';
    private const string PARA_PATTERN = '/^<para\b([^>]*)>(.*)<\/para>$/s';

    /** @throws FixerException */
    public function process(Violation $violation): FixPlan
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::PARA_PATTERN, $violation->content, $matches)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        if (count($violation->affectedRanges) !== 2) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $fixes = [];
        foreach ($violation->affectedRanges as $range) {
            $fixes[] = new Fix(
                $violation->filePath,
                $range->beginOffset,
                $range->untilOffset,
                self::TARGET_ELEMENT,
                $violation->sniffCode,
                self::SOURCE_ELEMENT,
            );
        }

        return new FixPlan(...$fixes);
    }
}
