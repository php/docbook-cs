<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Integration\Runner;

use DocbookCS\Runner\EntityExpansionMarker;
use DocbookCS\Runner\EntityPreprocessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(EntityExpansionMarker::class),
    CoversClass(EntityPreprocessor::class),
]
final class EntityExpansionMarkerTest extends TestCase
{
    #[Test]
    public function itMarksXmlExpansionsForParsingOnly(): void
    {
        $preprocessor = new EntityPreprocessor([
            'expanded' => '<para>Expanded</para>',
        ]);
        $source = '<root>&expanded;</root>';
        $document = $this->parse($preprocessor->processForParsing($source));
        $para = $document->getElementsByTagName('para')->item(0);
        $root = $document->documentElement;

        self::assertSame('<root><para>Expanded</para></root>', $preprocessor->process($source));
        self::assertInstanceOf(\DOMElement::class, $para);
        self::assertInstanceOf(\DOMElement::class, $root);
        self::assertTrue(EntityExpansionMarker::contains($para));
        self::assertFalse(EntityExpansionMarker::contains($root));
    }

    #[Test]
    public function itRecognizesNestedExpansionMarkers(): void
    {
        $preprocessor = new EntityPreprocessor([
            'outer' => '<wrapper>&inner;<after/></wrapper>',
            'inner' => '<para/>',
        ]);
        $document = $this->parse($preprocessor->processForParsing('<root>&outer;</root>'));

        foreach (['wrapper', 'para', 'after'] as $elementName) {
            $element = $document->getElementsByTagName($elementName)->item(0);
            self::assertInstanceOf(\DOMElement::class, $element);
            self::assertTrue(EntityExpansionMarker::contains($element));
        }
    }

    #[Test]
    public function itDoesNotExpandLiteralEntityText(): void
    {
        $preprocessor = new EntityPreprocessor(['value' => 'expanded']);
        $source = '<root><!-- &value; --><![CDATA[&value;]]><?test &value;?>&value;</root>';

        self::assertSame(
            '<root><!-- &value; --><![CDATA[&value;]]><?test &value;?>expanded</root>',
            $preprocessor->process($source),
        );
    }

    private function parse(string $xml): \DOMDocument
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        return $document;
    }
}
