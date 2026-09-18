<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

final class MixedUnionDetector
{
    /** @return list<array{beginOffset: int, untilOffset: int}> */
    public static function matches(string $source): array
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
                $members = array_map('strtolower', $type['members']);
                if (count($members) >= 2 && in_array('mixed', $members, true)) {
                    $unions[] = [
                        'beginOffset' => $type['beginOffset'],
                        'untilOffset' => $offset + strlen($tag),
                    ];
                }
            } elseif ($stack !== [] && $stack[array_key_last($stack)]['union']) {
                $stack[array_key_last($stack)]['members'][] = $content;
            }
        }

        return $unions;
    }
}
