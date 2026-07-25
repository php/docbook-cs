<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\AttributeOrderFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

/**
 * Ensures that when an element has both xml:id and xmlns (or xmlns:*)
 * attributes, xml:id appears first.
 *
 * This is a stylistic convention in the PHP documentation project:
 * identity attributes should precede namespace declarations.
 */
final class AttributeOrderSniff extends AbstractSniff implements Fixable
{
    private const string OPENING_TAG_PATTERN = '/<([a-zA-Z0-9:_-]+)\b([^<>]*?)>/';
    private const string ATTRIBUTE_NAME_PATTERN = '/([a-zA-Z0-9:_-]+)\s*=/';
    private const string REPORTING_MESSAGE = 'Element <%s>: xml:id should appear before xmlns attributes.';

    public static function getCode(): string
    {
        return 'DocbookCS.AttributeOrder';
    }

    public static function getFixerClassName(): string
    {
        return AttributeOrderFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a matched tag offset lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];

        // Match ONLY opening tags (skip closing, comments, xml decl)
        preg_match_all(
            self::OPENING_TAG_PATTERN,
            $this->maskNonElementMarkup($file->content),
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[0] as $i => [$fullMatch, $offset]) {
            $tagName = $matches[1][$i][0];
            $attrString = $matches[2][$i][0];

            // Skip if no relevant attributes at all (fast path)
            if (
                !str_contains($attrString, 'xml:id') ||
                !str_contains($attrString, 'xmlns')
            ) {
                continue;
            }

            $beginOffset = (int) $offset;

            $this->checkAttributes(
                $tagName,
                $attrString,
                $file->path,
                SourceRange::fromFile(
                    $file,
                    $beginOffset,
                    $beginOffset + strlen($fullMatch),
                ),
                $violations,
            );
        }

        return $violations;
    }

    /**
     * @param list<\DocbookCS\Violation\Violation> &$violations
     * @throws \InvalidArgumentException if the affected ranges are inconsistent
     */
    private function checkAttributes(
        string $tagName,
        string $attrString,
        string $filePath,
        SourceRange $affectedRange,
        array &$violations,
    ): void {
        preg_match_all(self::ATTRIBUTE_NAME_PATTERN, $attrString, $matches);
        $attributes = $matches[1];

        $xmlIdPos = null;
        $xmlnsPos = PHP_INT_MAX;

        foreach ($attributes as $i => $name) {
            if ($name === 'xml:id') {
                $xmlIdPos = $i;
            }

            if (
                $name === 'xmlns' ||
                str_starts_with($name, 'xmlns:')
            ) {
                $xmlnsPos = min($xmlnsPos, $i);
            }
        }

        if ($xmlIdPos === null || $xmlnsPos === PHP_INT_MAX || $xmlIdPos <= $xmlnsPos) {
            return;
        }

        $violations[] = $this->createViolation(
            $filePath,
            sprintf(self::REPORTING_MESSAGE, $tagName),
            [$affectedRange],
        );
    }
}
