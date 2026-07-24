<?php

declare(strict_types=1);

namespace DocbookCS\Config;

final readonly class SniffEntry
{
    /**
     * @param array<string, string> $properties
     * @throws \InvalidArgumentException if $className is empty or only whitespace.
     */
    public function __construct(
        public string $className,
        public array $properties = [],
    ) {
        if (trim($className) === '') {
            throw new \InvalidArgumentException('Sniff class name must not be empty.');
        }
    }

    public function getProperty(string $name, ?string $default = null): ?string
    {
        return $this->properties[$name] ?? $default;
    }
}
