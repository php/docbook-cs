<?php

declare(strict_types=1);

namespace DocbookCS\Fix\Fixer;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixerException;
use DocbookCS\Violation\Violation;

/**
 * Backward-compatible aggregate fixer for WhitespaceSniff.
 * New configurations should use the focused whitespace fixers.
 */
final class WhitespaceFixer implements Fixer
{
    /** @throws FixerException */
    public function process(Violation $violation): Fix
    {
        if ($violation->content === null) {
            throw FixerException::cannotFixMissingContent();
        }

        $fixed = rtrim($violation->content, " \t");

        if (preg_match('/^[ \t]+/', $fixed, $matches)) {
            $fixedIndent = str_replace("\t", ' ', $matches[0]);
            $fixed = $fixedIndent . substr($fixed, strlen($matches[0]));
        }

        if ($fixed === $violation->content) {
            throw FixerException::cannotFixInvalidContent($violation);
        }

        return new Fix(
            $violation->filePath,
            $violation->beginOffset,
            $violation->untilOffset,
            $fixed,
            $violation->sniffCode,
        );
    }
}
