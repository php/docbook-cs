<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Sniff\AbstractSniff;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Sniff\SimparaSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AbstractSniff::class),
    CoversClass(EntityExpansionMarker::class),
    CoversClass(EntityPreprocessor::class),
    CoversClass(ExceptionNameSniff::class),
    CoversClass(SimparaSniff::class),
    //
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
    UsesClass(Violation::class),
]
final class EntityExpandedSniffTest extends TestCase
{
    #[Test]
    public function simparaIgnoresExpandedAndSelfClosingElements(): void
    {
        $source = '<root><para/><para>Source</para>&expanded;</root>';
        $document = $this->processedDocument($source, '<para>Expanded</para>');

        $violations = new SimparaSniff()->process($document, new File('file.xml', $source));

        self::assertCount(1, $violations);
        self::assertSame('para', $violations[0]->rangeOne()->content);
        self::assertSame('para', $violations[0]->rangeTwo()->content);
    }

    #[Test]
    public function exceptionNameIgnoresExpandedElements(): void
    {
        $source = '<root><classname>RuntimeException</classname>&expanded;</root>';
        $document = $this->processedDocument($source, '<classname>ExpandedException</classname>');

        $violations = new ExceptionNameSniff()->process($document, new File('file.xml', $source));

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
    }

    private function processedDocument(string $source, string $expanded): \DOMDocument
    {
        $content = new EntityPreprocessor(['expanded' => $expanded])->processForParsing($source);
        $document = new \DOMDocument();
        $document->loadXML($content);

        return $document;
    }
}
