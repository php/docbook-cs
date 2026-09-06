<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\IndentationAnalyzer;
use DocbookCS\Sniff\IndentationSniff;
use DocbookCS\Sniff\TrailingWhitespaceSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(File::class),
    CoversClass(Line::class),
    CoversClass(IndentationSniff::class),
    CoversClass(TrailingWhitespaceSniff::class),
    CoversClass(Violation::class),
    //
    UsesClass(IndentationAnalyzer::class),
    UsesClass(SourceRange::class),
]
final class WhitespaceConcernSniffsTest extends TestCase
{
    #[Test]
    public function itReportsOnlyTrailingWhitespaceAsAffected(): void
    {
        $content = "<root>  \r\n</root>";
        $violations = new TrailingWhitespaceSniff()->process(
            $this->createDocument($content),
            new File('file.xml', $content),
        );

        self::assertCount(1, $violations);
        self::assertSame('DocbookCS.TrailingWhitespace', $violations[0]->sniffCode);
        self::assertSame('Trailing whitespace detected.', $violations[0]->message);
        self::assertSame('  ', $violations[0]->rangeOne()->content);
        self::assertSame(strlen('<root>'), $violations[0]->rangeOne()->beginOffset);
        self::assertSame(strlen('<root>  '), $violations[0]->rangeOne()->untilOffset);
        self::assertSame(1, $violations[0]->rangeOne()->line);
    }

    #[Test]
    public function itReportsOnlyLeadingIndentationAsAffected(): void
    {
        $content = "<root>\n \t<tag/>\n</root>";
        $lineOffset = strlen("<root>\n");
        $violations = new IndentationSniff()->process(
            $this->createDocument($content),
            new File('file.xml', $content),
        );

        self::assertCount(1, $violations);
        self::assertSame('DocbookCS.Indentation', $violations[0]->sniffCode);
        self::assertSame('Expected indentation of 1 space.', $violations[0]->message);
        self::assertSame(" \t", $violations[0]->rangeOne()->content);
        self::assertSame($lineOffset, $violations[0]->rangeOne()->beginOffset);
        self::assertSame($lineOffset + 2, $violations[0]->rangeOne()->untilOffset);
        self::assertSame(2, $violations[0]->rangeOne()->line);
    }

    #[Test]
    public function itReportsDisjointConcernsOnTheSameLine(): void
    {
        $content = "<root>\n \t<tag/>  \n</root>";
        $document = $this->createDocument($content);

        $source = new File('file.xml', $content);
        $indentation = new IndentationSniff()->process($document, $source)[0];
        $trailing = new TrailingWhitespaceSniff()->process($document, $source)[0];

        self::assertSame(2, $indentation->rangeOne()->line);
        self::assertSame(2, $trailing->rangeOne()->line);
        self::assertLessThanOrEqual(
            $trailing->rangeOne()->beginOffset,
            $indentation->rangeOne()->untilOffset,
        );
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
