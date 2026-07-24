<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\WhitespaceFixer;
use DocbookCS\Source\File;

/**
 * Backward-compatible aggregate of the focused whitespace rules.
 * New configurations should use TrailingWhitespaceSniff and MixedIndentationSniff.
 */
final class WhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string LINE_ENDING_PATTERN = '/(\r\n|\n|\r)/';
    private const string WHITESPACE_PATTERN = '/([ \t]+$)|^(\t* +\t+|\t+ +\t*)|^( +)\t/';

    public static function getCode(): string
    {
        return 'DocbookCS.Whitespace';
    }

    public static function fixerClassName(): string
    {
        return WhitespaceFixer::class;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];
        $offset = 0;
        $line = 1;

        $lines = preg_split(self::LINE_ENDING_PATTERN, $file->content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($lines === false) {
            throw new \LogicException('Could not split source content into lines.'); // @codeCoverageIgnore
        }

        for ($i = 0; $i < count($lines); $i += 2) {
            $lineContent = $lines[$i];
            $lineContentLength = strlen($lineContent);
            $lineEnding = $lines[$i + 1] ?? '';

            if (preg_match(self::WHITESPACE_PATTERN, $lineContent, $matches)) {
                $message = match (true) {
                    !empty($matches[1]) => 'Trailing whitespace detected.',
                    !empty($matches[2]) || !empty($matches[3]) => 'Mixed tabs and spaces in indentation.',
                    default => 'Inconsistent indentation.', // @codeCoverageIgnore
                };

                $violations[] = $this->createViolation(
                    $file->path,
                    $line,
                    $offset,
                    $offset + $lineContentLength,
                    $message,
                    $lineContent,
                );
            }

            $offset += $lineContentLength + strlen($lineEnding);
            $line++;
        }

        return $violations;
    }
}
