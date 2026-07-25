<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Support\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\Fixer\Fixer;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class ToggleElementFixer implements Fixer
{
    public function process(Violation $violation): Fix
    {
        $affectedRange = $violation->rangeOne();
        if ($affectedRange->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        $replacement = match ($affectedRange->content) {
            '<alpha/>' => '<beta/>',
            '<beta/>' => '<alpha/>',
            default => throw FixerException::cannotFixInvalidContent($violation),
        };

        return Fix::fromViolationAndRange($violation, $affectedRange, $replacement);
    }
}
