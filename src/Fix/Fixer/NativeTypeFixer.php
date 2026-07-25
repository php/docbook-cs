<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

final class NativeTypeFixer implements Fixer
{
    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        $range = $violation->rangeOne();
        if ($range->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        if ($violation->message === 'A union containing mixed is redundant and should be mixed.') {
            if (preg_match('/^<type\b[^>]*\bclass\s*=\s*(["\'])union\1[^>]*>.*<\/type>$/is', $range->content) !== 1) {
                throw FixerException::cannotFixInvalidContent($violation);
            }

            return Fix::fromViolationAndRange($violation, $range, '<type>mixed</type>');
        }

        if (preg_match('/should be written as "([a-z]+)"\.$/', $violation->message, $matches) !== 1) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return Fix::fromViolationAndRange($violation, $range, $matches[1]);
    }
}
