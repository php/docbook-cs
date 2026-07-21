<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\AttributeOrderSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AttributeOrderSniff::class),
    CoversClass(Violation::class),
]
final class AttributeOrderSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itIgnoresElementsWithoutRelevantAttributes(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root><tag foo="bar"/></root>';
        $doc = $this->createDocument('<root/>');

        self::assertSame(
            [],
            $sniff->process($doc, $content, 'test.xml')
        );
    }

    #[Test]
    public function itDoesNotFlagWhenXmlIdComesFirst(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root><tag xml:id="a" xmlns="urn:test"/></root>';
        $doc = $this->createDocument('<root/>');

        self::assertSame(
            [],
            $sniff->process($doc, $content, 'test.xml')
        );
    }

    #[Test]
    public function itFlagsWhenXmlIdComesAfterXmlns(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root><tag xmlns="urn:test" xml:id="a"/></root>';
        $doc = $this->createDocument('<root/>');

        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString(
            'xml:id should appear before xmlns',
            $violations[0]->message
        );
    }

    #[Test]
    public function itHandlesXmlnsPrefixedAttributes(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root><tag xmlns:foo="urn:test" xml:id="a"/></root>';
        $doc = $this->createDocument('<root/>');

        self::assertCount(
            1,
            $sniff->process($doc, $content, 'file.xml')
        );
    }

    #[Test]
    public function itHandlesMultipleElements(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root>
            <a xml:id="ok" xmlns="urn:test"/>
            <b xmlns="urn:test" xml:id="wrong"/>
            <c xml:id="ok2" xmlns:foo="urn:test"/>
            <d xmlns:bar="urn:test" xml:id="wrong2"/>
        </root>';

        $doc = $this->createDocument('<root/>');

        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(2, $violations);
    }

    #[Test]
    public function itReportsCorrectLineNumber(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = "<root>\n" .
            "  <tag xmlns=\"urn:test\" xml:id=\"a\"/>\n" .
            "</root>";

        $doc = $this->createDocument('<root/>');

        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame(2, $violations[0]->line);
    }

    #[Test]
    public function itReturnsEmptyWhenContentIsEmpty(): void
    {
        $sniff = new AttributeOrderSniff();

        $doc = $this->createDocument('<root/>');

        self::assertSame(
            [],
            $sniff->process($doc, '', 'file.xml')
        );
    }
}
