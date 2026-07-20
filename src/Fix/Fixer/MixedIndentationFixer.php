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
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (
            !preg_match(self::INDENTATION_PATTERN, $violation->content)
            || !str_contains($violation->content, ' ')
            || !str_contains($violation->content, "\t")
        ) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            filePath: $violation->filePath,
            beginOffset: $violation->beginOffset,
            untilOffset: $violation->untilOffset,
            replacement: str_replace("\t", ' ', $violation->content),
            sniffCode: $violation->sniffCode,
            expectedContent: $violation->content,
        );
    }
}
