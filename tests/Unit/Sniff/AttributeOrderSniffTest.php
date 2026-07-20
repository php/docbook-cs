<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Sniff\AttributeOrderSniff;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(AttributeOrderSniff::class),
    CoversClass(Violation::class),
    //
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
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
            $sniff->process($doc, new File('test.xml', $content))
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
            $sniff->process($doc, new File('test.xml', $content))
        );
    }

    #[Test]
    public function itFlagsWhenXmlIdComesAfterXmlns(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root><tag xmlns="urn:test" xml:id="a"/></root>';
        $doc = $this->createDocument('<root/>');

        $violations = $sniff->process($doc, new File('file.xml', $content));

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
            $sniff->process($doc, new File('file.xml', $content))
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

        $violations = $sniff->process($doc, new File('file.xml', $content));

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

        $violations = $sniff->process($doc, new File('file.xml', $content));

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
            $sniff->process($doc, new File('file.xml', ''))
        );
    }

    #[Test]
    public function itAddsSourceContent(): void
    {
        $sniff = new AttributeOrderSniff();

        $content = '<root xmlns="urn:test" xml:id="root"/>';
        $doc = $this->createDocument('<root/>');

        $violations = $sniff->process($doc, new File('file.xml', $content));

        self::assertCount(1, $violations);
        self::assertSame('<root xmlns="urn:test" xml:id="root"/>', $violations[0]->content);
        self::assertSame(0, $violations[0]->beginOffset);
        self::assertSame(38, $violations[0]->untilOffset);
        self::assertSame(1, $violations[0]->line);
    }
}
