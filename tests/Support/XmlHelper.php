<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Support;

trait XmlHelper
{
    private function xml(string $body): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
$body
XML;
    }
}
