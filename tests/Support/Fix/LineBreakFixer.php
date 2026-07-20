<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Support\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class LineBreakFixer implements Fixer
{
    public function process(Violation $violation): Fix
    {
        if ($violation->content !== '<line-break/>') {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            filePath: $violation->filePath,
            beginOffset: $violation->beginOffset,
            untilOffset: $violation->untilOffset,
            replacement: "\n",
            sniffCode: $violation->sniffCode,
            expectedContent: $violation->content,
        );
    }
}
