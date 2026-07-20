<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\SimparaSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(SimparaSniff::class),
    CoversClass(Violation::class),
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
        $doc = $this->createDocument('<root><para>Text</para></root>');

        self::assertCount(1, new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itFlagsAllowedInlineElements(): void
    {
        $doc = $this->createDocument(
            '<root><para>Text <emphasis>inline</emphasis></para></root>'
        );

        self::assertCount(1, new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagUnknownElement(): void
    {
        $doc = $this->createDocument(
            '<root><para><itemizedlist/></para></root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itHandlesMultipleParas(): void
    {
        $sniff = new SimparaSniff();

        $doc = $this->createDocument(
            '<root>
                <para>Inline</para>
                <para><itemizedlist/></para>
                <para><emphasis>ok</emphasis></para>
            </root>'
        );

        $violations = $sniff->process($doc, '', 'file.xml');

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itSupportsAdditionalInlineElements(): void
    {
        $sniff = new SimparaSniff();
        $sniff->setProperty('additionalInlineElements', 'custom');

        $doc = $this->createDocument(
            '<root><para><custom/></para></root>'
        );

        self::assertCount(1, $sniff->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagWhenCustomElementNotAllowed(): void
    {
        $doc = $this->createDocument(
            '<root><para><custom/></para></root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itReportsCorrectLineNumber(): void
    {
        $doc = $this->createDocument(
            '<root>' . PHP_EOL .
            '  <para>Text</para>' . PHP_EOL .
            '</root>'
        );

        $violations = new SimparaSniff()->process($doc, '', 'file.xml');

        self::assertSame(2, $violations[0]->line);
    }

    #[Test]
    public function itDoesNotFlagParaInsideFormalpara(): void
    {
        $doc = $this->createDocument(
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text</para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagParaWithInlineContentInsideFormalpara(): void
    {
        $doc = $this->createDocument(
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text with <emphasis>emphasis</emphasis></para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itStillFlagsParasOutsideFormalparaWhenSiblingIsFormalpara(): void
    {
        $doc = $this->createDocument(
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Inside formalpara</para>
                </formalpara>
                <para>Outside formalpara</para>
            </root>'
        );

        $violations = new SimparaSniff()->process($doc, '', 'file.xml');

        self::assertCount(1, $violations);
    }

    #[Test]
    public function itDoesNotFlagParaInFormalparaRegardlessOfCase(): void
    {
        $doc = $this->createDocument(
            '<root>
                <formalpara>
                    <title>Title</title>
                    <para>Text</para>
                </formalpara>
            </root>'
        );

        self::assertSame([], new SimparaSniff()->process($doc, '', 'file.xml'));
    }
}
