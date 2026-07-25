<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\WhitespaceFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

/**
 * Backward-compatible aggregate of the focused whitespace rules.
 * New configurations should use TrailingWhitespaceSniff and MixedIndentationSniff.
 */
final class WhitespaceSniff extends AbstractSniff implements Fixable
{
    private const string TRAILING_WHITESPACE_MESSAGE = 'Trailing whitespace detected.';
    private const string MIXED_INDENTATION_MESSAGE = 'Mixed tabs and spaces in indentation.';
    private const string INCONSISTENT_INDENTATION_MESSAGE = 'Inconsistent indentation.';
    private const string LINE_ENDING_PATTERN = '/(\r\n|\n|\r)/';
    private const string WHITESPACE_PATTERN = '/([ \t]+$)|^(\t* +\t+|\t+ +\t*)|^( +)\t/';
    public static function getCode(): string
    {
        return 'DocbookCS.Whitespace';
    }

    public static function getFixerClassName(): string
    {
        return WhitespaceFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \LogicException if source content cannot be split into lines
     * @throws \OutOfBoundsException if a generated source range lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];
        $offset = 0;

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
                    !empty($matches[1]) => self::TRAILING_WHITESPACE_MESSAGE,
                    !empty($matches[2]) || !empty($matches[3]) => self::MIXED_INDENTATION_MESSAGE,
                    default => self::INCONSISTENT_INDENTATION_MESSAGE, // @codeCoverageIgnore
                };

                $violations[] = $this->createViolation(
                    $file->path,
                    $message,
                    [SourceRange::fromFile($file, $offset, $offset + $lineContentLength)],
                );
            }

            $offset += $lineContentLength + strlen($lineEnding);
        }

        return $violations;
    }
}
