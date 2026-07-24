<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Source\File;
use DocbookCS\Violation\Violation;

/**
 * A sniff receives a DOMDocument (already loaded) and its source file,
 * then returns zero or more findings for reports and optional fixes.
 */
interface SniffInterface
{
    /**
     * Unique, human-readable code for this sniff (e.g. "DocbookCS.MySniff").
     */
    public static function getCode(): string;

    /**
     * Apply the sniff to the given document.
     *
     * @return list<Violation>
     */
    public function process(\DOMDocument $document, File $file): array;

    /**
     * Accept a key/value property from the configuration.
     */
    public function setProperty(string $name, string $value): void;
}
