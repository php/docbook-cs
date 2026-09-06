<?php

declare(strict_types=1);

namespace DocbookCS;

use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;

/**
 * @phpstan-type ElementState array{
 *     name: string,
 *     preservesWhitespace: bool
 * }
 *
 * @phpstan-type IndentationMismatch array{
 *     range: SourceRange,
 *     expectedDepth: int
 * }
 *
 * @phpstan-type SourceTag array{
 *     beginOffset: int,  untilOffset: int,  name: string,  closing: bool, selfClosing: bool,  xmlSpace: string|null
 * }
 */
final class IndentationAnalyzer
{
    private const string INDENTATION_PATTERN = '/^[ \t]*/';
    private const string TAG_PATTERN = "/<(?<closing>\/)?(?<name>[a-z_:][a-z0-9_.:-]*)"
        . "(?<attributes>(?:\"[^\"]*\"|'[^']*'|[^'\">])*)>/is";
    private const string XML_SPACE_PATTERN = '/\sxml:space\s*=\s*(["\'])(preserve|default)\1/';

    /**
     * The complete set of elements using db.verbatim.attributes in DocBook 5.2+.
     * Their whitespace must be preserved even without xml:space="preserve".
     */
    private const array VERBATIM_ELEMENTS = [
        'address',
        'classsynopsisinfo',
        'funcsynopsisinfo',
        'literallayout',
        'programlisting',
        'screen',
        'synopsis',
        'synopsisinfo',
    ];

    /**
     * @return \Generator<int, IndentationMismatch>
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     */
    public function analyze(File $file): \Generator
    {
        $maskedContent = $file->contentWithNonElementMarkupMasked();

        $tagIndex = 0;
        $tags = $this->findTags($maskedContent);

        /** @var list<ElementState> $elementStack */
        $elementStack = [];

        foreach ($file->lines() as $line) {

            $firstTag = $tags[$tagIndex] ?? null;
            $mismatch = $this->mismatchForLine($line, $maskedContent, $firstTag, $elementStack);

            if ($mismatch !== null) {
                yield $mismatch;
            }

            while (isset($tags[$tagIndex]) && $tags[$tagIndex]['untilOffset'] <= $line->offsetAfterContent()) {
                $this->applyTagToElementStack($tags[$tagIndex], $elementStack);
                $tagIndex++;
            }
        }
    }

    /**
     * @param SourceTag|null $firstTag
     * @param list<ElementState> $elementStack
     * @return IndentationMismatch|null
     * @throws \InvalidArgumentException if the generated source range is inconsistent
     */
    private function mismatchForLine(Line $line, string $maskedContent, ?array $firstTag, array $elementStack): ?array
    {
        preg_match(self::INDENTATION_PATTERN, $line->content, $matches);

        $indentation = $matches[0] ?? '';
        $contentOffset = $line->beginOffset + strlen($indentation);

        if (!$this->shouldCheckLine($line, $contentOffset, $maskedContent, $firstTag)) {
            return null;
        }

        $startsWithClosingTag = $firstTag !== null
            && $firstTag['beginOffset'] === $contentOffset
            && $firstTag['closing'];

        $insidePreservedRegion = $elementStack !== []
            && $elementStack[array_key_last($elementStack)]['preservesWhitespace'];

        if (
            $insidePreservedRegion
            && !$this->closesPreservedRegion($startsWithClosingTag, $firstTag, $elementStack)
        ) {
            return null;
        }

        $expectedDepth = max(0, count($elementStack) - ($startsWithClosingTag ? 1 : 0));

        if ($indentation === str_repeat(' ', $expectedDepth)) {
            return null;
        }

        return [
            'range' => new SourceRange(
                line: $line->number,
                beginOffset: $line->beginOffset,
                untilOffset: $contentOffset,
                content: $indentation,
            ),
            'expectedDepth' => $expectedDepth,
        ];
    }

    /** @param SourceTag|null $firstTag */
    private function shouldCheckLine(Line $line, int $contentOffset, string $maskedContent, ?array $firstTag): bool
    {
        if ($contentOffset >= $line->offsetAfterContent()) {
            return false;
        }

        if ($maskedContent[$contentOffset] === ' ') {
            return false;
        }

        if ($firstTag === null) {
            return true;
        }

        return $contentOffset <= $firstTag['beginOffset'] || $contentOffset >= $firstTag['untilOffset'];
    }

    /**
     * @param SourceTag|null $tag
     * @param list<ElementState> $elementStack
     */
    private function closesPreservedRegion(bool $startsWithClosingTag, ?array $tag, array $elementStack): bool
    {
        if (!$startsWithClosingTag || $tag === null || $elementStack === []) {
            return false;
        }

        $elementIndex = array_key_last($elementStack);
        $element = $elementStack[$elementIndex];

        $parentPreservesWhitespace = $elementIndex > 0
            && $elementStack[$elementIndex - 1]['preservesWhitespace'];

        return $element['preservesWhitespace']
            && !$parentPreservesWhitespace
            && $element['name'] === $tag['name'];
    }

    /**
     * @param SourceTag $tag
     * @param list<ElementState> &$elementStack
     */
    private function applyTagToElementStack(array $tag, array &$elementStack): void
    {
        if ($tag['closing']) {
            array_pop($elementStack);
            return;
        }

        if ($tag['selfClosing']) {
            return;
        }

        $inherited = $elementStack !== []
            && $elementStack[array_key_last($elementStack)]['preservesWhitespace'];

        $elementStack[] = [
            'name' => $tag['name'],
            'preservesWhitespace' => $this->preservesWhitespace($tag['name'], $tag['xmlSpace'], $inherited),
        ];
    }

    private function preservesWhitespace(string $elementName, ?string $xmlSpace, bool $inherited): bool
    {
        $separator = strrpos($elementName, ':');

        $localName = strtolower($separator === false
            ? $elementName
            : substr($elementName, $separator + 1));

        if (in_array($localName, self::VERBATIM_ELEMENTS, true)) {
            return true;
        }

        return match ($xmlSpace) {
            'preserve' => true,
            'default' => false,
            default => $inherited,
        };
    }

    /** @return list<SourceTag> */
    private function findTags(string $maskedContent): array
    {
        $tags = [];

        preg_match_all(self::TAG_PATTERN, $maskedContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => [$tag, $beginOffset]) {

            preg_match(self::XML_SPACE_PATTERN, $matches['attributes'][$index][0], $xmlSpaceMatches);

            $tags[] = [
                'beginOffset' => (int) $beginOffset,
                'untilOffset' => (int) $beginOffset + strlen($tag),
                'name' => $matches['name'][$index][0],
                'closing' => $matches['closing'][$index][0] !== '',
                'selfClosing' => str_ends_with(rtrim($tag), '/>'),
                'xmlSpace' => $xmlSpaceMatches[2] ?? null,
            ];
        }

        return $tags;
    }
}
