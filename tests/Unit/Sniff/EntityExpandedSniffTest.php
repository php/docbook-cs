<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\SimparaSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AbstractSniff::class),
    CoversClass(EntityExpansionMarker::class),
    CoversClass(EntityPreprocessor::class),
    CoversClass(ExceptionNameSniff::class),
    CoversClass(SimparaSniff::class),
    CoversClass(Violation::class),
]
final class EntityExpandedSniffTest extends TestCase
{
    #[Test]
    public function simparaIgnoresExpandedElements(): void
    {
        $source = '<root><para>Source</para>&expanded;</root>';
        $document = $this->processedDocument($source, '<para>Expanded</para>');

        $violations = new SimparaSniff()->process($document, $source, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame(1, $violations[0]->line);
    }

    #[Test]
    public function exceptionNameIgnoresExpandedElements(): void
    {
        $source = '<root><classname>RuntimeException</classname>&expanded;</root>';
        $document = $this->processedDocument(
            $source,
            '<classname>ExpandedException</classname>',
        );

        $violations = new ExceptionNameSniff()->process($document, $source, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame(1, $violations[0]->line);
    }

    private function processedDocument(string $source, string $expanded): \DOMDocument
    {
        $content = new EntityPreprocessor(['expanded' => $expanded])->processForParsing($source);
        $document = new \DOMDocument();
        $document->loadXML($content);

        return $document;
    }
}
