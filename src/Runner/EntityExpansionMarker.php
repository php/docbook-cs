<?php

declare(strict_types=1);

namespace DocbookCS\Runner;

final class EntityExpansionMarker
{
    private const string START = 'docbook-cs:entity-expansion:start';
    private const string END = 'docbook-cs:entity-expansion:end';

    public static function wrap(string $content): string
    {
        return '<!--' . self::START . '-->'
            . $content
            . '<!--' . self::END . '-->';
    }

    public static function contains(\DOMNode $node): bool
    {
        for ($current = $node; $current->parentNode !== null; $current = $current->parentNode) {
            if (self::isBetweenMarkers($current)) {
                return true;
            }
        }

        return false;
    }

    private static function isBetweenMarkers(\DOMNode $node): bool
    {
        $nestedMarkers = 0;

        for ($sibling = $node->previousSibling; $sibling !== null; $sibling = $sibling->previousSibling) {
            if (self::isEnd($sibling)) {
                $nestedMarkers++;
                continue;
            }

            if (!self::isStart($sibling)) {
                continue;
            }

            if ($nestedMarkers === 0) {
                return true;
            }

            $nestedMarkers--;
        }

        return false;
    }

    private static function isStart(\DOMNode $node): bool
    {
        return $node instanceof \DOMComment && $node->textContent === self::START;
    }

    private static function isEnd(\DOMNode $node): bool
    {
        return $node instanceof \DOMComment && $node->textContent === self::END;
    }
}
