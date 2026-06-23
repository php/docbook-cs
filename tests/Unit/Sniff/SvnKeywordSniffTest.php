<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\FileReport;
use DocbookCS\Report\Report;
use DocbookCS\Report\Violation;
use DocbookCS\Runner\EntityPreprocessor;
use DocbookCS\Runner\XmlFileProcessor;
use DocbookCS\Sniff\SvnKeywordSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SvnKeywordSniff::class)]
#[CoversClass(Violation::class)]
#[CoversClass(XmlFileProcessor::class)]
#[CoversClass(EntityPreprocessor::class)]
#[CoversClass(FileReport::class)]
#[CoversClass(Report::class)]
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
        // The message still points at the real marker location (line 2) so the
        // report is actionable even outside diff mode.
        self::assertStringContainsString('on line 2', $violations[0]->message);
    }

    #[Test]
    public function itStaysRelevantWhenAChangeIsFarFromTheMarker(): void
    {
        // Marker on line 2, change on line 5: the root anchor must keep the
        // violation relevant through XmlFileProcessor's diff filter.
        $report = $this->processor()->processString(
            '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<!-- $Revision$ -->' . PHP_EOL .
            '<chapter>' . PHP_EOL .
            '  <simpara>line 4</simpara>' . PHP_EOL .
            '  <simpara>line 5</simpara>' . PHP_EOL .
            '</chapter>',
            'f.xml',
            [5],
        );

        self::assertSame(1, $report->getViolationCount());
        self::assertStringContainsString('on line 2', $report->getViolations()[0]->message);
    }

    #[Test]
    public function itIsFilteredOutWhenNoChangeTouchesTheDocument(): void
    {
        // Change only on line 1 (XML declaration, above the root span): the
        // diff filter must drop the violation, proving the filter is real and
        // not a no-op.
        $report = $this->processor()->processString(
            '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<!-- $Revision$ -->' . PHP_EOL .
            '<chapter>' . PHP_EOL .
            '  <simpara>line 4</simpara>' . PHP_EOL .
            '</chapter>',
            'f.xml',
            [1],
        );

        self::assertSame(0, $report->getViolationCount());
    }

    private function processor(): XmlFileProcessor
    {
        return new XmlFileProcessor([new SvnKeywordSniff()], new EntityPreprocessor([]));
    }
}
