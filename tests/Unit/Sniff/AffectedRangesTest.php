<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
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
    CoversClass(ExceptionNameSniff::class),
    CoversClass(SimparaSniff::class),
    CoversClass(SourceRange::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(Violation::class),
]
final class AffectedRangesTest extends TestCase
{
    #[Test]
    public function simparaIdentifiesBothElementNamesWithoutChangingTheFinding(): void
    {
        $content = "<root>\n<para>Text</para>\n</root>";
        $violation = new SimparaSniff()->process(
            $this->document($content),
            new File('file.xml', $content),
        )[0];

        self::assertSame('<para>Text</para>', $violation->content);
        self::assertSame((int) strpos($content, '<para>'), $violation->beginOffset);
        self::assertEquals([
            new SourceRange(2, 8, 12),
            new SourceRange(2, 19, 23),
        ], $violation->affectedRanges);
    }

    #[Test]
    public function exceptionNameIdentifiesElementNamesOnDifferentLines(): void
    {
        $content = "<root>\n<classname>RuntimeException\n</classname>\n</root>";
        $violation = new ExceptionNameSniff()->process(
            $this->document($content),
            new File('file.xml', $content),
        )[0];

        self::assertSame("<classname>RuntimeException\n</classname>", $violation->content);
        self::assertEquals([
            new SourceRange(2, 8, 17),
            new SourceRange(3, 37, 46),
        ], $violation->affectedRanges);
    }

    private function document(string $content): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return $document;
    }
}
