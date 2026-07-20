<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\FixerException;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Violation\Violation;

final class ExceptionNameFixer implements Fixer
{
    private const string SOURCE_ELEMENT = 'classname';
    private const string TARGET_ELEMENT = 'exceptionname';
    private const string CLASSNAME_PATTERN = '/^<classname\b([^>]*)>([^<]*)<\/classname>$/';

    /** @throws FixerException */
    public function process(Violation $violation): FixPlan
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::CLASSNAME_PATTERN, $violation->content, $matches)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        $text = trim($matches[2]);

        if ($text === '' || !ExceptionNameSniff::looksLikeException($text)) {
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
