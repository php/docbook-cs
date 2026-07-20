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
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if (!preg_match(self::WHITESPACE_PATTERN, $violation->content)) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            filePath: $violation->filePath,
            beginOffset: $violation->beginOffset,
            untilOffset: $violation->untilOffset,
            replacement: '',
            sniffCode: $violation->sniffCode,
            expectedContent: $violation->content,
        );
    }
}
