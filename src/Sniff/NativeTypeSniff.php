<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\NativeTypeFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

/**
 * @extends AbstractSniff<string>
 * @implements Fixable<string>
 */
final class NativeTypeSniff extends AbstractSniff implements Fixable
{
    private const array NATIVE_TYPES = [
        'array',
        'bool',
        'callable',
        'false',
        'float',
        'int',
        'iterable',
        'mixed',
        'never',
        'null',
        'object',
        'resource',
        'string',
        'true',
        'void',
    ];

    private const array TYPE_ALIASES = [
        'boolean' => 'bool',
        'double' => 'float',
        'integer' => 'int',
        'real' => 'float',
    ];

    public static function getCode(): string
    {
        return 'DocbookCS.NativeType';
    }

    public static function getFixerClassName(): string
    {
        return NativeTypeFixer::class;
    }

    /**
     * @throws \InvalidArgumentException if a generated source range is inconsistent
     * @throws \OutOfBoundsException if a matched type lies outside the source
     */
    public function process(\DOMDocument $document, File $file): array
    {
        $source = $this->maskNonElementMarkup($file->content);
        $synopsisRanges = $this->synopsisRanges($source);
        $redundantMixedUnions = array_values(array_filter(
            MixedUnionDetector::matches($source),
            fn(array $union): bool => $this->isInsideSynopsis($union['beginOffset'], $synopsisRanges),
        ));
        $violations = [];

        preg_match_all('/<type\b[^>]*>([^<]*)<\/type>/i', $source, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$typeName, $offset]) {
            $offset = (int) $offset;
            if (!$this->isInsideSynopsis($offset, $synopsisRanges)) {
                continue;
            }

            $canonical = $this->canonicalType(trim($typeName));
            if ($canonical === null || $canonical === trim($typeName)) {
                continue;
            }

            $leadingWhitespace = strlen($typeName) - strlen(ltrim($typeName));
            $beginOffset = $offset + $leadingWhitespace;
            $untilOffset = $beginOffset + strlen(trim($typeName));
            if ($this->isInsideUnion($beginOffset, $untilOffset, $redundantMixedUnions)) {
                continue;
            }

            $violations[] = $this->createViolation(
                $file->path,
                sprintf('Native type "%s" should be written as "%s".', trim($typeName), $canonical),
                [SourceRange::fromFile($file, $beginOffset, $untilOffset)],
                $canonical,
            );
        }

        return $violations;
    }

    private function canonicalType(string $typeName): ?string
    {
        $lowercase = strtolower($typeName);

        return self::TYPE_ALIASES[$lowercase]
            ?? (in_array($lowercase, self::NATIVE_TYPES, true) ? $lowercase : null);
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

    /** @param list<array{beginOffset: int, untilOffset: int}> $unions */
    private function isInsideUnion(int $beginOffset, int $untilOffset, array $unions): bool
    {
        foreach ($unions as $union) {
            if ($beginOffset >= $union['beginOffset'] && $untilOffset <= $union['untilOffset']) {
                return true;
            }
        }

        return false;
    }
}
