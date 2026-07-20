<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Fix;

use DocbookCS\Fix\Fix;
use DocbookCS\Fix\FixApplier;
use DocbookCS\Fix\FixPlan;
use DocbookCS\Fix\Fixer\SimparaFixer;
use DocbookCS\Fix\FixResult;
use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\RunMode;
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
    CoversClass(Fix::class),
    CoversClass(FixApplier::class),
    CoversClass(FixResult::class),
    CoversClass(RunMode::class),
    CoversClass(SimparaFixer::class),
    CoversClass(SimparaSniff::class),
    CoversClass(Violation::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(FixPlan::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class SimparaFixerTest extends TestCase
{
    #[Test]
    public function itReplacesSimpleParaTags(): void
    {
        $content = '<root><para>Text <emphasis>inline</emphasis></para></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(1, $violations);
        self::assertSame('<para>Text <emphasis>inline</emphasis></para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame('<root><simpara>Text <emphasis>inline</emphasis></simpara></root>', $result->file->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itPreservesParaAttributes(): void
    {
        $content = '<root><para xml:id="example">Text</para></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(1, $violations);
        self::assertSame('<para xml:id="example">Text</para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame('<root><simpara xml:id="example">Text</simpara></root>', $result->file->content);
        self::assertSame(1, $result->applied);
    }

    #[Test]
    public function itCanFixNestedInnerParas(): void
    {
        $content = '<root><para>Text<note><para>Inner</para></note></para></root>';
        $document = $this->createDocument($content);
        $source = new File('file.xml', $content);

        $violations = new SimparaSniff(RunMode::Fix)->process($document, $source);

        self::assertCount(1, $violations);
        self::assertSame('<para>Inner</para>', $violations[0]->content);

        $fix = new SimparaFixer()->process($violations[0]);

        $result = new FixApplier()->apply($source, [$fix]);

        self::assertSame(
            '<root><para>Text<note><simpara>Inner</simpara></note></para></root>',
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
