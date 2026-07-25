<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\MixedUnionFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

/**
 * @extends AbstractSniff<string>
 * @implements Fixable<string>
 */
final class MixedUnionSniff extends AbstractSniff implements Fixable
{
    public static function getCode(): string
    {
        return 'DocbookCS.MixedUnion';
    }

    public static function getFixerClassName(): string
    {
        return MixedUnionFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a matched union lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $source = $this->maskNonElementMarkup($file->content);
        $synopsisRanges = $this->synopsisRanges($source);
        $violations = [];

        foreach (MixedUnionDetector::matches($source) as $union) {
            if (!$this->isInsideSynopsis($union['beginOffset'], $synopsisRanges)) {
                continue;
            }

            $violations[] = $this->createViolation(
                $file->path,
                'A union containing mixed is redundant and should be mixed.',
                [SourceRange::fromFile($file, $union['beginOffset'], $union['untilOffset'])],
                '<type>mixed</type>',
            );
        }

        return $violations;
    }

    /** @return list<array{beginOffset: int, untilOffset: int}> */
    private function synopsisRanges(string $source): array
    {
        preg_match_all('/<\/?(?:method|constructor)synopsis\b[^>]*>/i', $source, $matches, PREG_OFFSET_CAPTURE);
        $stack = [];
        $ranges = [];
        foreach ($matches[0] as [$tag, $offset]) {
            $offset = (int) $offset;
            if (!str_starts_with($tag, '</')) {
                $stack[] = $offset;
            } elseif (null !== $beginOffset = array_pop($stack)) {
                $ranges[] = ['beginOffset' => $beginOffset, 'untilOffset' => $offset + strlen($tag)];
            }
        }

        return $ranges;
    }

    /** @param list<array{beginOffset: int, untilOffset: int}> $ranges */
    private function isInsideSynopsis(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['beginOffset'] && $offset < $range['untilOffset']) {
                return true;
            }
        }

        return false;
    }
}
