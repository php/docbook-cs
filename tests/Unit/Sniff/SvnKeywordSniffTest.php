<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\SvnKeywordSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvnKeywordSniff::class)]
#[CoversClass(Violation::class)]
final class SvnKeywordSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itFlagsBareRevisionMarker(): void
    {
        $doc = $this->createDocument('<!-- $Revision$ --><root/>');

        self::assertCount(1, new SvnKeywordSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itFlagsExpandedRevisionMarker(): void
    {
        $doc = $this->createDocument('<!-- $Revision: 348113 $ --><root/>');

        $violations = new SvnKeywordSniff()->process($doc, '', 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('$Revision: 348113 $', $violations[0]->message);
    }

    #[Test]
    public function itFlagsOtherSvnKeywords(): void
    {
        $doc = $this->createDocument('<!-- $Id$ --><root/>');

        self::assertCount(1, new SvnKeywordSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagEnRevisionHeader(): void
    {
        $doc = $this->createDocument(
            '<!-- EN-Revision: a1b2c3d Maintainer: lacatoire Status: ready --><root/>'
        );

        self::assertSame([], new SvnKeywordSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagProseMentioningRevision(): void
    {
        $doc = $this->createDocument('<root><para>The Revision number</para></root>');

        self::assertSame([], new SvnKeywordSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itFlagsMultipleMarkers(): void
    {
        $doc = $this->createDocument('<!-- $Revision$ --><!-- $Id$ --><root/>');

        self::assertCount(2, new SvnKeywordSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itSupportsAdditionalKeywords(): void
    {
        $sniff = new SvnKeywordSniff();
        $sniff->setProperty('additionalKeywords', 'Custom');

        $doc = $this->createDocument('<!-- $Custom$ --><root/>');

        self::assertCount(1, $sniff->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itAnchorsViolationToTheRootElementLine(): void
    {
        // The marker sits on line 2 but the violation is anchored to the root
        // element (line 3) so it stays relevant to any change in the document
        // when running in diff mode.
        $doc = $this->createDocument(
            '<?xml version="1.0"?>' . PHP_EOL .
            '<!-- $Revision$ -->' . PHP_EOL .
            '<root>' . PHP_EOL .
            '  <para>Text</para>' . PHP_EOL .
            '</root>'
        );

        $violations = new SvnKeywordSniff()->process($doc, '', 'file.xml');

        self::assertSame(3, $violations[0]->line);
    }
}
