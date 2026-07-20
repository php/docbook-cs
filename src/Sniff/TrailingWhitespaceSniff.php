<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Source\File;

final class TrailingWhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string TRAILING_WHITESPACE_PATTERN = '/[ \t]+$/';
    private const string MESSAGE = 'Trailing whitespace detected.';

    public static function getCode(): string
    {
        return 'DocbookCS.TrailingWhitespace';
    }

    public static function fixerClassName(): string
    {
        return TrailingWhitespaceFixer::class;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];

        foreach ($file->lines() as $line) {
            if (!preg_match(self::TRAILING_WHITESPACE_PATTERN, $line->content, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            [$whitespace, $relativeOffset] = $matches[0];
            $beginOffset = $line->beginOffset + (int) $relativeOffset;

            $violations[] = $this->createViolation(
                $file->path,
                $line->number,
                $beginOffset,
                $beginOffset + strlen($whitespace),
                self::MESSAGE,
                $whitespace,
            );
        }

        return $violations;
    }
}
