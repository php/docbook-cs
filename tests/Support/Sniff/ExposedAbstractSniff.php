<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Support\Sniff;

use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Source\File;
use DocbookCS\Violation\Severity;

final class ExposedAbstractSniff extends AbstractSniff
{
    public static function getCode(): string
    {
        return 'test.sniff';
    }

    public function process(\DOMDocument $document, File $file): array
    {
        return [];
    }

    public function exposeGet(string $name, string $default = ''): string
    {
        return $this->getProperty($name, $default);
    }

    public function exposeSeverity(): Severity
    {
        return $this->severity;
    }
}
