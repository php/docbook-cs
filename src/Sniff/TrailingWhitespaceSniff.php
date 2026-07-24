<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\TrailingWhitespaceFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

final class TrailingWhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string TRAILING_WHITESPACE_PATTERN = '/[ \t]+$/';
    private const string REPORTING_MESSAGE = 'Trailing whitespace detected.';

    public static function getCode(): string
    {
        return 'DocbookCS.TrailingWhitespace';
    }

    public static function fixerClassName(): string
    {
        return TrailingWhitespaceFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a generated source range lies outside the source
     */
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
                self::REPORTING_MESSAGE,
                [SourceRange::fromFile($file, $beginOffset, $beginOffset + strlen($whitespace))],
            );
        }

        return $violations;
    }
}
