<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Runner\EntityExpansionMarker;
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
    CoversClass(SimparaSniff::class),
    CoversClass(Violation::class),
    //
    UsesClass(EntityExpansionMarker::class),
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
]
final class SimparaSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itFlagsPlainTextPara(): void
    {
        $doc = $this->createDocument($content =
            '<root><para>Text</para></root>'
        );

        $violations = new SimparaSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertSame('<para>Text</para>', $violations[0]->content);
        self::assertSame((int) strpos($content, '<para>'), $violations[0]->beginOffset);
        self::assertSame((int) strpos($content, '</para>') + strlen('</para>'), $violations[0]->untilOffset);
    }

    #[Test]
    public function itFlagsAllowedInlineElements(): void
    {
        $doc = $this->createDocument($content =
            '<root><para>Text <emphasis>inline</emphasis></para></root>'
        );

        self::assertCount(1, new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itDoesNotFlagUnknownElement(): void
    {
        $doc = $this->createDocument($content =
            '<root><para><itemizedlist/></para></root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itHandlesMultipleParas(): void
    {
        $sniff = new SimparaSniff();

        $doc = $this->createDocument($content =
            '<root>
                <para>Inline</para>
                <para><itemizedlist/></para>
                <para><emphasis>ok</emphasis></para>
            </root>'
        );

        $violations = $sniff->process($doc, new File('file.xml', $content));

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itSupportsAdditionalInlineElements(): void
    {
        $sniff = new SimparaSniff();
        $sniff->setProperty('additionalInlineElements', 'custom');

        $doc = $this->createDocument($content =
            '<root><para><custom/></para></root>'
        );

        self::assertCount(1, $sniff->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itDoesNotFlagWhenCustomElementNotAllowed(): void
    {
        $doc = $this->createDocument($content =
            '<root><para><custom/></para></root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itReportsCorrectLineNumber(): void
    {
        $doc = $this->createDocument($content =
            '<root>' . PHP_EOL .
            '  <para>Text</para>' . PHP_EOL .
            '</root>'
        );

        $violations = new SimparaSniff()->process($doc, new File('file.xml', $content));

        self::assertSame(2, $violations[0]->line);
    }

    #[Test]
    public function itIgnoresTagShapedTextOutsideElementsWhenMappingSource(): void
    {
        $content = <<<'XML'
            <!DOCTYPE root [<!ENTITY sample "<para>Declared</para>">]>
            <root>
                <!-- <para>Commented</para> -->
                <![CDATA[<para>CDATA</para>]]>
                <?sample <para>Instruction</para>?>
                <para>Source</para>
            </root>
            XML;
        $doc = $this->createDocument($content);

        $violations = new SimparaSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertSame('<para>Source</para>', $violations[0]->content);
        self::assertSame(6, $violations[0]->line);
    }

    #[Test]
    public function itDoesNotFlagParaInsideFormalpara(): void
    {
        $doc = $this->createDocument($content =
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text</para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itDoesNotFlagParaWithInlineContentInsideFormalpara(): void
    {
        $doc = $this->createDocument($content =
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text with <emphasis>emphasis</emphasis></para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }

    #[Test]
    public function itStillFlagsParasOutsideFormalparaWhenSiblingIsFormalpara(): void
    {
        $doc = $this->createDocument($content =
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Inside formalpara</para>
                </formalpara>
                <para>Outside formalpara</para>
            </root>'
        );

        $violations = new SimparaSniff()->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
    }

    #[Test]
    public function itDoesNotFlagParaInFormalparaRegardlessOfCase(): void
    {
        $doc = $this->createDocument($content =
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text</para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, new File('file.xml', $content)));
    }
}
