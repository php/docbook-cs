<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Fix\Fixer\NativeTypeFixer;
use DocbookCS\Source\File;
use DocbookCS\Violation\SourceRange;

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
            $violations[] = $this->createViolation(
                $file->path,
                sprintf('Native type "%s" should be written as "%s".', trim($typeName), $canonical),
                [SourceRange::fromFile($file, $beginOffset, $beginOffset + strlen(trim($typeName)))],
            );
        }

        foreach ($this->unionMatches($source) as $union) {
            if (!$this->isInsideSynopsis($union['beginOffset'], $synopsisRanges)) {
                continue;
            }

            $members = array_map('strtolower', $union['members']);
            if (count($members) < 2 || !in_array('mixed', $members, true)) {
                continue;
            }

            $violations[] = $this->createViolation(
                $file->path,
                'A union containing mixed is redundant and should be mixed.',
                [SourceRange::fromFile($file, $union['beginOffset'], $union['untilOffset'])],
            );
        }

        usort(
            $violations,
            static fn(\DocbookCS\Violation\Violation $a, \DocbookCS\Violation\Violation $b): int =>
                $a->rangeOne()->beginOffset <=> $b->rangeOne()->beginOffset,
        );

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

    /**
     * @return list<array{beginOffset: int, untilOffset: int, members: list<string>}>
     */
    private function unionMatches(string $source): array
    {
        preg_match_all('/<\/?type\b[^>]*>/i', $source, $matches, PREG_OFFSET_CAPTURE);
        /** @var list<array{beginOffset: int, contentOffset: int, union: bool, members: list<string>}> $stack */
        $stack = [];
        $unions = [];

        foreach ($matches[0] as [$tag, $offset]) {
            $offset = (int) $offset;
            if (!str_starts_with($tag, '</')) {
                if (str_ends_with(rtrim($tag), '/>')) {
                    if ($stack !== [] && $stack[array_key_last($stack)]['union']) {
                        $stack[array_key_last($stack)]['members'][] = '';
                    }
                    continue;
                }

                $stack[] = [
                    'beginOffset' => $offset,
                    'contentOffset' => $offset + strlen($tag),
                    'union' => preg_match('/\bclass\s*=\s*(["\'])union\1/i', $tag) === 1,
                    'members' => [],
                ];
                continue;
            }

            if (null === $type = array_pop($stack)) {
                continue;
            }

            $content = trim(substr($source, $type['contentOffset'], $offset - $type['contentOffset']));
            if ($type['union']) {
                $unions[] = [
                    'beginOffset' => $type['beginOffset'],
                    'untilOffset' => $offset + strlen($tag),
                    'members' => $type['members'],
                ];
            } elseif ($stack !== [] && $stack[array_key_last($stack)]['union']) {
                $stack[array_key_last($stack)]['members'][] = $content;
            }
        }

        return $unions;
    }
}
