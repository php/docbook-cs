<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Severity;
use DocbookCS\Report\Violation;
use DocbookCS\Sniff\SecondPersonSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecondPersonSniff::class)]
#[CoversClass(Violation::class)]
final class SecondPersonSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itFlagsYou(): void
    {
        $doc = $this->createDocument('<root><para>If you call the function.</para></root>');

        self::assertCount(1, new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itFlagsYourAndYourself(): void
    {
        $doc = $this->createDocument(
            '<root><para>Pass your value to configure yourself.</para></root>'
        );

        self::assertCount(2, new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIsCaseInsensitive(): void
    {
        $doc = $this->createDocument('<root><para>You should note this.</para></root>');

        self::assertCount(1, new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagWordsContainingAPronoun(): void
    {
        $doc = $this->createDocument(
            '<root><para>The young user employs a yourstore token.</para></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresProgramlisting(): void
    {
        $doc = $this->createDocument(
            '<root><programlisting>echo "you and your input";</programlisting></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresScreen(): void
    {
        $doc = $this->createDocument('<root><screen>your output here</screen></root>');

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itCountsEachOccurrence(): void
    {
        $doc = $this->createDocument(
            '<root><para>you and you and you</para></root>'
        );

        self::assertCount(3, new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itReportsCorrectLineNumber(): void
    {
        $doc = $this->createDocument(
            '<root>' . PHP_EOL .
            '  <para>you are here</para>' . PHP_EOL .
            '</root>'
        );

        $violations = new SecondPersonSniff()->process($doc, '', 'file.xml');

        self::assertSame(2, $violations[0]->line);
    }

    #[Test]
    public function itUsesWarningSeverity(): void
    {
        $doc = $this->createDocument('<root><para>you</para></root>');

        $violations = new SecondPersonSniff()->process($doc, '', 'file.xml');

        self::assertSame(Severity::WARNING, $violations[0]->severity);
    }

    #[Test]
    public function itSupportsAdditionalPronouns(): void
    {
        $sniff = new SecondPersonSniff();
        $sniff->setProperty('additionalPronouns', 'we, us');

        $doc = $this->createDocument('<root><para>we return it to us.</para></root>');

        self::assertCount(2, $sniff->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itDoesNotFlagImpersonalProse(): void
    {
        $doc = $this->createDocument(
            '<root><para>The function returns the value to the caller.</para></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }
}
