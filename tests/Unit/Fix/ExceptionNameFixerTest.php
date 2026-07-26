<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\ExceptionNameFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Sniff\ExceptionNameSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(ExceptionNameFixer::class),
    CoversClass(ExceptionNameSniff::class),
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(Violation::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class ExceptionNameFixerTest extends TestCase
{
    #[Test]
    public function itReplacesSimpleClassnameTags(): void
    {
        $content = '<root><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $sniffer = new ExceptionNameSniff();
        $violations = $sniffer->process($document, $source);

        $beginOffset = (int) strpos($content, '<classname>');
        $untilOffset = (int) strpos($content, '</classname>');

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
        self::assertEquals([
            new SourceRange(1, $beginOffset + 1, $beginOffset + 10, 'classname'),
            new SourceRange(1, $untilOffset + 2, $untilOffset + 11, 'classname'),
        ], $violations[0]->affectedRanges);

        $fix = new ($sniffer::getFixerClassName())()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame('<root><exceptionname>RuntimeException</exceptionname></root>', $result->file->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesClassnameAttributes(): void
    {
        $content = '<root><classname linkend="runtime-exception">RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff()->process($document, $source);

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><exceptionname linkend="runtime-exception">RuntimeException</exceptionname></root>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itKeepsSourceContentAlignedAfterRegularClassnames(): void
    {
        $content = '<root><classname>RegularClass</classname><classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff()->process($document, $source);

        $sourceContent = '<classname>RuntimeException</classname>';
        $beginOffset = (int) strpos($content, $sourceContent);
        $untilOffset = (int) strpos($content, '</classname>', $beginOffset);

        self::assertCount(1, $violations);
        self::assertSame('classname', $violations[0]->rangeOne()->content);
        self::assertSame('classname', $violations[0]->rangeTwo()->content);
        self::assertEquals([
            new SourceRange(1, $beginOffset + 1, $beginOffset + 10, 'classname'),
            new SourceRange(1, $untilOffset + 2, $untilOffset + 11, 'classname'),
        ], $violations[0]->affectedRanges);

        $fix = new ExceptionNameFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><classname>RegularClass</classname><exceptionname>RuntimeException</exceptionname></root>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesTagShapedTextInsideComments(): void
    {
        $content = '<root><!-- <classname>CommentedException</classname> -->'
            . '<classname>RuntimeException</classname></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new ExceptionNameSniff()->process($document, $source);

        self::assertCount(1, $violations);

        $fix = new ExceptionNameFixer()->process($violations[0]);
        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><!-- <classname>CommentedException</classname> -->'
                . '<exceptionname>RuntimeException</exceptionname></root>',
            $result->file->content,
        );
        self::assertSame(1, $result->applied);
    }

    private function createDocument(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
