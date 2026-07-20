<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

/**
 * Detects exception/error class names wrapped in <classname> that
 * should use <exceptionname> instead.
 *
 * DocBook provides <exceptionname> specifically for "the name of an
 * exception." When a <classname> element's text content matches a
 * known exception/error pattern, this sniff flags it.
 */
final class ExceptionNameSniff extends AbstractSniff implements Fixable
{
    private const string ELEMENT_NAME = 'classname';

    /**
     * Default suffixes that indicate the class is an exception or error.
     * @var list<string>
     */
    private const array DEFAULT_SUFFIXES = [
        'Exception',
        'Error',
        'Throwable',
    ];

    private const string CLASSNAME_PATTERN = '/<classname\b[^>]*>([^<]*)<\/classname>/';

    public static function getCode(): string
    {
        return 'DocbookCS.ExceptionName';
    }

    public static function fixerClassName(): string
    {
        return ExceptionNameFixer::class;
    }

    /**
     * @throws \LogicException
     * @throws \OutOfBoundsException if a matched tag offset lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $violations = [];
        $sourceMatchIndex = 0;

        $classnames = $document->getElementsByTagName('classname');
        if ($classnames->length === 0) {
            return [];
        }

        $sourceMatches = $this->sourceMatches($file);

        /** @var \DOMElement $node */
        foreach ($classnames as $node) {
            if (!$this->isSourceBacked($node)) {
                continue;
            }

            $text = trim($node->textContent);
            $match = $sourceMatches[$sourceMatchIndex] ?? null;
            $sourceMatchIndex++;

            if ($text === '') {
                continue;
            }

            if ($node->parentNode instanceof \DOMElement && $node->parentNode->localName === 'ooclass') {
                continue;
            }

            if (!self::looksLikeException($text)) {
                continue;
            }

            if ($match === null || $match['text'] !== $text) {
                throw new \LogicException('Could not map classname violation to source content.');
            }

            $violations[] = $this->createViolation(
                $file->path,
                $match['affectedRanges'][0]->line,
                $match['beginOffset'],
                $match['untilOffset'],
                sprintf('"%s" is wrapped in <classname> but should use <exceptionname>.', $text),
                $match['content'],
                affectedRanges: $match['affectedRanges'],
            );
        }

        return $violations;
    }

    public static function looksLikeException(string $text): bool
    {
        $parts = explode('\\', $text);
        $baseName = end($parts);

        return array_any(self::DEFAULT_SUFFIXES, static fn(string $suffix): bool => str_ends_with($baseName, $suffix));
    }

    /**
     * @return list<array{
     *     beginOffset: int,
     *     untilOffset: int,
     *     content: string,
     *     text: string,
     *     affectedRanges: non-empty-list<SourceRange>
     * }>
     * @throws \OutOfBoundsException if a matched tag offset lies outside the source
     */
    private function sourceMatches(File $file): array
    {
        preg_match_all(self::CLASSNAME_PATTERN, $file->content, $matches, PREG_OFFSET_CAPTURE);

        $sourceMatches = [];
        foreach ($matches[0] as $i => [$fullMatch, $offset]) {
            $offset = (int) $offset;
            $closingOffset = $offset + (int) strrpos($fullMatch, '</classname>');
            $sourceMatches[] = [
                'beginOffset' => $offset,
                'untilOffset' => $offset + strlen($fullMatch),
                'content' => $fullMatch,
                'text' => trim($matches[1][$i][0]),
                'affectedRanges' => [
                    new SourceRange(
                        $file->lineAtOffset($offset)->number,
                        $offset + 1,
                        $offset + 1 + strlen(self::ELEMENT_NAME),
                    ),
                    new SourceRange(
                        $file->lineAtOffset($closingOffset)->number,
                        $closingOffset + 2,
                        $closingOffset + 2 + strlen(self::ELEMENT_NAME),
                    ),
                ],
            ];
        }

        return $sourceMatches;
    }
}
