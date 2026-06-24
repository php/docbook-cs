<?php

declare(strict_types=1);

namespace DocbookCS\Tests\Unit\Sniff;

use DocbookCS\Report\Violation;
use DocbookCS\Sniff\ListInParaSniff;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListInParaSniff::class)]
#[CoversClass(Violation::class)]
final class ListInParaSniffTest extends TestCase
{
    private function createDocument(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        return $doc;
    }

    #[Test]
    public function itReturnsEmptyWhenNoLists(): void
    {
        $content = '<root><para>Just text.</para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'test.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itFlagsSimplelistInPara(): void
    {
        $content = '<root><para><simplelist><member>a</member></simplelist></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('simplelist', $violations[0]->message);
        self::assertSame('file.xml', $violations[0]->filePath);
    }

    #[Test]
    public function itFlagsVariablelistItemizedlistAndOrderedlistInPara(): void
    {
        $content = '<root>
                <para>
                    <variablelist>
                        <varlistentry><term>t</term><listitem><para>x</para></listitem></varlistentry>
                    </variablelist>
                </para>
                <para><itemizedlist><listitem><para>x</para></listitem></itemizedlist></para>
                <para><orderedlist><listitem><para>x</para></listitem></orderedlist></para>
            </root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertCount(3, $violations);
    }

    #[Test]
    public function itDoesNotFlagListAsDirectChildOfSection(): void
    {
        $content = '<refsect1><simplelist><member>a</member></simplelist></refsect1>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itDoesNotFlagListNestedDeeperThanPara(): void
    {
        // The list's direct parent is <note>, not <para>, so it is allowed.
        $content = '<root><para><note><simplelist><member>a</member></simplelist></note></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itFlagsTableAsSoleChildOfPara(): void
    {
        $content = '<root><para><table><title>t</title></table></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('table', $violations[0]->message);
    }

    #[Test]
    public function itFlagsInformaltableAsSoleChildOfPara(): void
    {
        $content = '<root><para><informaltable><tgroup cols="1"/></informaltable></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('informaltable', $violations[0]->message);
    }

    #[Test]
    public function itDoesNotFlagBlockPrecededByIntroText(): void
    {
        // A leading sentence followed by a <table> is a valid construct.
        $content = '<root><para>The following constants are defined:'
            . '<table><title>t</title></table></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itDoesNotFlagListAlongsideAnotherElement(): void
    {
        // The list is not the sole element child, so it is not flagged.
        $content = '<root><para><simplelist><member>a</member></simplelist>'
            . '<note><para>x</para></note></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itFlagsAdditionalListElement(): void
    {
        $content = '<root><para><segmentedlist><seglistitem><seg>a</seg></seglistitem></segmentedlist></para></root>';
        $doc = $this->createDocument($content);

        $sniff = new ListInParaSniff();
        $sniff->setProperty('additionalListElements', 'segmentedlist');
        $violations = $sniff->process($doc, $content, 'file.xml');

        self::assertCount(1, $violations);
        self::assertStringContainsString('segmentedlist', $violations[0]->message);
    }

    #[Test]
    public function itDoesNotFlagAdditionalListElementWhenNotConfigured(): void
    {
        $content = '<root><para><segmentedlist><seglistitem><seg>a</seg></seglistitem></segmentedlist></para></root>';
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertSame([], $violations);
    }

    #[Test]
    public function itReportsViolationsInDocumentOrder(): void
    {
        // itemizedlist is on line 2, simplelist on line 3; violations must
        // follow line order regardless of the configured element ordering.
        $content = "<root>\n"
            . "<para><itemizedlist><listitem><para>x</para></listitem></itemizedlist></para>\n"
            . "<para><simplelist><member>a</member></simplelist></para>\n"
            . "</root>";
        $doc = $this->createDocument($content);
        $violations = new ListInParaSniff()->process($doc, $content, 'file.xml');

        self::assertCount(2, $violations);
        self::assertStringContainsString('itemizedlist', $violations[0]->message);
        self::assertStringContainsString('simplelist', $violations[1]->message);
        self::assertLessThan($violations[1]->line, $violations[0]->line);
    }
}
