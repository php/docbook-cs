<?php

declare(strict_types=1);

namespace DocbookCS\Sniff;

use DocbookCS\Runner\RunMode;
use DocbookCS\Source\File;

/**
 * A sniff receives a DOMDocument (already loaded) and its source file,
 * then returns zero or more findings for reports and optional fixes.
 */
interface SniffInterface
{
    public RunMode $mode { get; }

    public function __construct(RunMode $mode);

    /**
     * Unique, human-readable code for this sniff (e.g. "DocbookCS.MySniff").
     */
    public static function getCode(): string;

    /**
     * Apply the sniff to the given document.
     *
     * @return list<\DocbookCS\Violation\Violation>
     */
    public function process(\DOMDocument $document, File $file): array;

    /**
     * Accept a key/value property from the configuration.
     */
    public function setProperty(string $name, string $value): void;
}
