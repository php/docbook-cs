<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\MixedIndentationFixer;
use DocbookCS\Source\File;

final class MixedIndentationSniff extends AbstractSniff implements Fixable
{
    private const string INDENTATION_PATTERN = '/^[ \t]+/';
    private const string MESSAGE = 'Mixed tabs and spaces in indentation.';

    public static function getCode(): string
    {
        return 'DocbookCS.MixedIndentation';
    }

    public static function fixerClassName(): string
    {
        return MixedIndentationFixer::class;
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];

        foreach ($file->lines() as $line) {
            if (!preg_match(self::INDENTATION_PATTERN, $line->content, $matches)) {
                continue;
            }

            $indentation = $matches[0];
            if (!str_contains($indentation, ' ') || !str_contains($indentation, "\t")) {
                continue;
            }

            $violations[] = $this->createViolation(
                $file->path,
                $line->number,
                $line->beginOffset,
                $line->beginOffset + strlen($indentation),
                self::MESSAGE,
                $indentation,
            );
        }

        return $violations;
    }
}
