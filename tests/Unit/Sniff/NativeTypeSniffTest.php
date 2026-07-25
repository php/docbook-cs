<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Sniff\NativeTypeSniff;
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
    CoversClass(NativeTypeSniff::class),
    CoversClass(Violation::class),
    UsesClass(File::class),
    UsesClass(Line::class),
    UsesClass(SourceRange::class),
    UsesClass(MixedUnionDetector::class),
]
final class NativeTypeSniffTest extends TestCase
{
    #[Test]
    public function itAcceptsCanonicalNativeAndClassTypes(): void
    {
        $content = '<methodsynopsis><type>string</type><methodname>example</methodname>'
            . '<methodparam><type>ExampleClass</type><parameter>value</parameter></methodparam></methodsynopsis>';

        self::assertSame([], $this->process($content));
    }

    #[Test]
    public function itReportsNonCanonicalCasingAndAliases(): void
    {
        $content = '<methodsynopsis><type>Array</type><methodname>example</methodname>'
            . '<methodparam><type>integer</type><parameter>value</parameter></methodparam>'
            . '<methodparam><type>BOOLEAN</type><parameter>enabled</parameter></methodparam></methodsynopsis>';

        $violations = $this->process($content);

        self::assertCount(3, $violations);
        self::assertSame('array', $this->replacementFromMessage($violations[0]->message));
        self::assertSame('int', $this->replacementFromMessage($violations[1]->message));
        self::assertSame('bool', $this->replacementFromMessage($violations[2]->message));
        self::assertSame('Array', $violations[0]->rangeOne()->content);
        self::assertSame('array', $violations[0]->fixerData);
    }

    #[Test]
    public function itDefersMembersOfRedundantMixedUnionsToTheMixedUnionSniff(): void
    {
        $content = '<methodsynopsis><type class="union"><type>mixed</type><type>Array</type></type>'
            . '<methodname>example</methodname></methodsynopsis>';

        self::assertSame([], $this->process($content));
    }

    #[Test]
    public function itDoesNotInspectTypeElementsOutsideSynopsesOrInsideComments(): void
    {
        $content = '<root><para><type>String</type></para>'
            . '<!-- <methodsynopsis><type>Array</type></methodsynopsis> -->'
            . '<methodsynopsis><type>void</type><methodname>example</methodname></methodsynopsis></root>';

        self::assertSame([], $this->process($content));
    }

    #[Test]
    public function itReportsInSourceOrder(): void
    {
        $content = "<methodsynopsis>\n <type>String</type><methodname>example</methodname>\n"
            . " <methodparam><type>Callable</type><parameter>value</parameter></methodparam>\n</methodsynopsis>";

        $violations = $this->process($content);

        self::assertCount(2, $violations);
        self::assertSame(2, $violations[0]->rangeOne()->line);
        self::assertSame(3, $violations[1]->rangeOne()->line);
    }

    /** @return list<Violation> */
    private function process(string $content): array
    {
        $document = new \DOMDocument();
        $document->loadXML($content);

        return new NativeTypeSniff()->process($document, new File('file.xml', $content));
    }

    private function replacementFromMessage(string $message): string
    {
        self::assertSame(1, preg_match('/should be written as "([a-z]+)"/', $message, $matches));

        return $matches[1];
    }
}
