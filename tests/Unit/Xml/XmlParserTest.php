<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Xml;

use DocbookCS\Xml\XmlParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlParser::class)]
final class XmlParserTest extends TestCase
{
    #[Test]
    public function itParsesDocuments(): void
    {
        $document = new XmlParser()->parseDocument('<root/>');

        self::assertInstanceOf(\DOMDocument::class, $document);
        self::assertSame('root', $document->documentElement?->localName);
    }

    #[Test]
    public function itParsesElements(): void
    {
        $element = new XmlParser()->parseElement('<root/>');

        self::assertInstanceOf(\SimpleXMLElement::class, $element);
        self::assertSame('root', $element->getName());
    }

    #[Test]
    public function itReturnsParseErrorsForInvalidElements(): void
    {
        $error = new XmlParser()->parseElement('<root>');

        self::assertInstanceOf(\LibXMLError::class, $error);
        self::assertStringContainsString('Premature end of data', $error->message);
    }

    #[Test]
    public function itReturnsTheFirstParseErrorAndRestoresTheErrorMode(): void
    {
        $previousUseErrors = libxml_use_internal_errors(false);

        try {
            $error = new XmlParser()->parseDocument('<root>');

            self::assertInstanceOf(\LibXMLError::class, $error);
            self::assertStringContainsString('Premature end of data', $error->message);
            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }
}
