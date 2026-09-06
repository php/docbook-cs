<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\FileEmptyLastLineFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

final class FileEmptyLastLineSniffer extends AbstractSniff implements Fixable
{
    private const string FILE_END_PATTERN = '/(?:[\r\n]+|[^\r\n]*)\z/';
    private const string REPORTING_MESSAGE = 'File must end with exactly one empty (LF) line.';

    public static function getCode(): string
    {
        return 'DocbookCS.FileEmptyLastLine';
    }

    public static function getFixerClassName(): string
    {
        return FileEmptyLastLineFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a generated source range lies outside the source
     * @throws SniffException if the file ending cannot be identified
     */
    public function process(\DOMDocument $document, File $file): array
    {
        if (preg_match(self::FILE_END_PATTERN, $file->content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw SniffException::cannotIdentifyFileEnding();
        }

        [$affectedContent, $beginOffset] = $matches[0];

        if ($affectedContent === "\n") {
            return [];
        }

        return [
            $this->createViolation(
                $file->path,
                self::REPORTING_MESSAGE,
                [SourceRange::fromFile($file, $beginOffset, strlen($file->content))],
            ),
        ];
    }
}
