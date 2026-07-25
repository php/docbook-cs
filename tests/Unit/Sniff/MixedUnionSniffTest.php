<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Sniff\MixedUnionSniff;
use DocbookCS\Sniff\MixedUnionDetector;
use DocbookCS\Source\File;
use DocbookCS\Source\Line;
use DocbookCS\Violation\SourceRange;
use DocbookCS\Violation\Violation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[
    CoversClass(MixedUnionSniff::class),
    CoversClass(Violation::class),
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
    UsesClass(MixedUnionDetector::class),
]
final class MixedUnionSniffTest extends TestCase
{
    #[Test]
    public function itReportsMixedCombinedWithAnotherUnionMember(): void
    {
        $content = '<methodsynopsis><type class="union"><type>mixed</type><type>null</type></type>'
            . '<methodname>example</methodname></methodsynopsis>';

        $violations = $this->process($content);

        self::assertCount(1, $violations);
        self::assertSame('DocbookCS.MixedUnion', $violations[0]->sniffCode);
        self::assertStringContainsString('union containing mixed', $violations[0]->message);
        self::assertSame('<type>mixed</type>', $violations[0]->fixerData);
        self::assertSame(
            '<type class="union"><type>mixed</type><type>null</type></type>',
            $violations[0]->rangeOne()->content,
        );
    }

    #[Test]
    public function itAcceptsAUnionWithoutMixedAndMixedByItself(): void
    {
        $content = '<methodsynopsis><type class="union"><type>string</type><type>null</type></type>'
            . '<methodname>example</methodname><methodparam><type>mixed</type>'
            . '<parameter>value</parameter></methodparam></methodsynopsis>';

        self::assertSame([], $this->process($content));
    }

    #[Test]
    public function itDoesNotInspectUnionsOutsideSynopsesOrInsideComments(): void
    {
        $union = '<type class="union"><type>mixed</type><type>null</type></type>';
        $content = '<root><para>' . $union . '</para><!-- <methodsynopsis>' . $union
            . '</methodsynopsis> --><methodsynopsis><type>void</type>'
            . '<methodname>example</methodname></methodsynopsis></root>';

        self::assertSame([], $this->process($content));
    }

    /** @return list<Violation> */
    private function process(string $content): array
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return new MixedUnionSniff()->process($document, new File('file.xml', $content));
    }
}
