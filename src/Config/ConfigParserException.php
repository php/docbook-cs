<?php

declare(strict_types=1);

namespace DocbookCS\Config;

final class ConfigParserException extends \RuntimeException
{
    public static function fileNotFound(string $path): self
    {
        return new self(sprintf('Configuration file not found: %s', $path));
    }

    public static function invalidXml(string $path, string $reason): self
    {
        return new self(sprintf('Invalid XML in %s: %s', $path, $reason));
    }

    public static function missingElement(string $element): self
    {
        return new self(sprintf('Required element <%s> is missing.', $element));
    }

    public static function missingAttribute(string $element, string $attribute): self
    {
        return new self(sprintf('Element <%s> is missing required attribute "%s".', $element, $attribute));
    }
}
