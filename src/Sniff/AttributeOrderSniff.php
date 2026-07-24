<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

/**
 * Ensures that when an element has both xml:id and xmlns (or xmlns:*)
 * attributes, xml:id appears first.
 *
 * This is a stylistic convention in the PHP documentation project:
 * identity attributes should precede namespace declarations.
 */
final class AttributeOrderSniff extends AbstractSniff
{
    private const string REPORTING_MESSAGE = 'Element <%s>: xml:id should appear before xmlns attributes.';

    public function getCode(): string
    {
        return 'DocbookCS.AttributeOrder';
    }

    /** @throws \LogicException if an invalid severity level is configured */
    public function process(\DOMDocument $document, string $content, string $filePath): array
    {
        $violations = [];

        // Match ONLY opening tags (skip closing, comments, xml decl)
        preg_match_all('/<([a-zA-Z0-9:_-]+)\b([^<>]*?)>/s', $content, $matches, PREG_OFFSET_CAPTURE);

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

            $this->checkAttributes(
                $tagName,
                $attrString,
                $filePath,
                $this->lineFromOffset($content, (int)$offset),
                $violations
            );
        }

        return $violations;
    }

    /**
     * @param list<\DocbookCS\Report\Violation> &$violations
     * @throws \LogicException if an invalid severity level is configured
     */
    private function checkAttributes(
        string $tagName,
        string $attrString,
        string $filePath,
        int $line,
        array &$violations
    ): void {
        preg_match_all('/([a-zA-Z0-9:_-]+)\s*=/', $attrString, $matches);
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

        if ($xmlIdPos !== null && $xmlnsPos !== PHP_INT_MAX && $xmlIdPos > $xmlnsPos) {
            $violations[] = $this->createViolation(
                $filePath,
                $line,
                sprintf(self::REPORTING_MESSAGE, $tagName),
            );
        }
    }

    private function lineFromOffset(string $content, int $offset): int
    {
        return substr_count($content, "\n", 0, $offset) + 1;
    }
}
