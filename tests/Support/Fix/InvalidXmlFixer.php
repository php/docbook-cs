<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Support\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class InvalidXmlFixer implements Fixer
{
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();
        if ($affectedRange->content !== '<valid/>') {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            filePath: $violation->filePath,
            beginOffset: $affectedRange->beginOffset,
            untilOffset: $affectedRange->untilOffset,
            replacement: '<invalid>',
            sniffCode: $violation->sniffCode,
            expectedContent: $affectedRange->content,
        );
    }
}
