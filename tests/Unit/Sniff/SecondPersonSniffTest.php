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
    public function itIgnoresInlineLiteralValue(): void
    {
        $doc = $this->createDocument(
            '<root><para>The string <literal>you</literal> becomes uppercase.</para></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresInlineCodeElements(): void
    {
        $doc = $this->createDocument(
            '<root><para>Set <varname>your_token</varname> and <parameter>yourId</parameter>.</para></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresQuotedMaterial(): void
    {
        $doc = $this->createDocument(
            '<root><para><quote>you must not pass</quote> is from the spec.</para></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresPronounNestedDeepInSkippedAncestor(): void
    {
        $doc = $this->createDocument(
            '<root><programlisting><emphasis>you</emphasis></programlisting></root>'
        );

        self::assertSame([], new SecondPersonSniff()->process($doc, '', 'file.xml'));
    }

    #[Test]
    public function itIgnoresCdataSections(): void
    {
        $doc = $this->createDocument('<root><para><![CDATA[you raw text]]></para></root>');

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
    public function itReportsRealLineForPronounInMultiLineNode(): void
    {
        // <para> opens on line 2; "you" sits on line 3 of the source.
        $doc = $this->createDocument(
            '<root>' . PHP_EOL .
            '  <para>first line' . PHP_EOL .
            '   you here</para>' . PHP_EOL .
            '</root>'
        );

        $violations = new SecondPersonSniff()->process($doc, '', 'file.xml');

        self::assertCount(1, $violations);
        self::assertSame(3, $violations[0]->line);
    }

    #[Test]
    public function itUsesWarningSeverity(): void
    {
        $doc = $this->createDocument('<root><para>you</para></root>');

        $violations = new SecondPersonSniff()->process($doc, '', 'file.xml');

        self::assertSame(Severity::WARNING, $violations[0]->severity);
    }

    #[Test]
    public function itMentionsThePronounInTheMessage(): void
    {
        $doc = $this->createDocument('<root><para>your value</para></root>');

        $violations = new SecondPersonSniff()->process($doc, '', 'file.xml');

        self::assertStringContainsString('your', $violations[0]->message);
    }

    #[Test]
    public function itSupportsAdditionalPronouns(): void
    {
        $sniff = new SecondPersonSniff();
        $sniff->setProperty('additionalPronouns', 'we, our');

        $doc = $this->createDocument('<root><para>we return our result.</para></root>');

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
